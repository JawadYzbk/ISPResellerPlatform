<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\BillingRun;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class RunBulkRenewals implements Action
{
    public function __construct(private CreateRenewalInvoice $createRenewalInvoice) {}

    /** @param array<int, string> $servicePublicIds */
    public function handle(array $servicePublicIds, User $actor, string $idempotencyKey, ?CarbonImmutable $at = null): BillingRun
    {
        $selected = array_values(array_unique(array_map('strval', $servicePublicIds)));
        if ($selected === []) {
            throw new DomainException('Select at least one service for bulk billing.');
        }
        sort($selected);

        $periodKey = substr(hash('sha256', $idempotencyKey), 0, 32);

        return DB::transaction(function () use ($selected, $actor, $idempotencyKey, $periodKey, $at): BillingRun {
            $run = BillingRun::query()
                ->where('run_type', 'bulk_renewal')
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();
            $storedRows = [];

            if ($run === null) {
                $run = BillingRun::create([
                    'run_type' => 'bulk_renewal',
                    'period_key' => $periodKey,
                    'status' => 'running',
                    'started_at' => now(),
                    'metadata' => [
                        'idempotency_key' => $idempotencyKey,
                        'service_ids' => $selected,
                        'rows' => [],
                    ],
                ]);
            } else {
                $storedIds = array_values(array_map('strval', $run->metadata['service_ids'] ?? []));
                $storedRows = collect($run->metadata['rows'] ?? [])->keyBy('service_id')->all();
                if (array_diff($selected, $storedIds) !== []) {
                    throw new DomainException('This idempotency key was already used for a different service selection.');
                }
                if ($storedIds !== $selected) {
                    $nonFailed = array_filter(
                        $selected,
                        static fn (string $serviceId): bool => ($storedRows[$serviceId]['status'] ?? null) !== 'failed',
                    );
                    if ($nonFailed !== []) {
                        throw new DomainException('Only failed rows from this batch can be retried with a partial selection.');
                    }
                }
                if ($run->status === 'completed' && $run->failed_count === 0 && $storedIds === $selected) {
                    return $run;
                }
            }

            $services = Service::query()
                ->with(['customer', 'plan'])
                ->whereIn('public_id', $selected)
                ->get()
                ->keyBy('public_id');

            foreach ($selected as $servicePublicId) {
                $service = $services->get($servicePublicId);
                if (! $service instanceof Service) {
                    $storedRows[$servicePublicId] = [
                        'service_id' => $servicePublicId,
                        'status' => 'failed',
                        'message' => 'The service is not available in this workspace.',
                    ];

                    continue;
                }

                try {
                    $invoice = $this->createRenewalInvoice->handle($service->customer, $service, $actor);
                    $storedRows[$service->public_id] = [
                        'service_id' => $service->public_id,
                        'username' => $service->username,
                        'status' => 'processed',
                        'invoice_id' => $invoice->public_id,
                        'invoice_number' => $invoice->number,
                        'message' => "Invoice {$invoice->number} is ready for collection.",
                    ];
                } catch (DomainException $exception) {
                    $storedRows[$service->public_id] = [
                        'service_id' => $service->public_id,
                        'username' => $service->username,
                        'status' => 'failed',
                        'message' => $exception->getMessage(),
                    ];
                }
            }

            $rows = array_values($storedRows);
            $processed = count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'processed'));
            $failed = count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'failed'));

            $run->forceFill([
                'status' => $failed === 0 ? 'completed' : 'completed_with_errors',
                'processed_count' => $processed,
                'failed_count' => $failed,
                'completed_at' => now(),
                'last_error' => $failed === 0 ? null : 'One or more renewal rows require review.',
                'metadata' => [
                    'idempotency_key' => $idempotencyKey,
                    'service_ids' => $run->metadata['service_ids'] ?? $selected,
                    'rows' => $rows,
                    'processed_at' => ($at ?? CarbonImmutable::now())->toIso8601String(),
                ],
            ])->save();

            return $run->refresh();
        });
    }
}
