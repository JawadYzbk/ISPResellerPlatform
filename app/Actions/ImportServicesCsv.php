<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use App\Models\ImportBatch;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final readonly class ImportServicesCsv implements Action
{
    public function handle(Tenant $tenant, string $contents, string $filename, bool $dryRun = false): ImportBatch
    {
        return app(Tenancy::class)->run($tenant, function () use ($contents, $filename, $dryRun): ImportBatch {
            $rows = $this->parse($contents);
            $batch = ImportBatch::create(['type' => 'services', 'filename' => $filename, 'status' => $dryRun ? 'preview' : 'processing', 'total_rows' => count($rows)]);
            $report = $this->validateRows($rows);
            $successful = count(array_filter($report, fn (array $row): bool => $row['status'] === 'valid'));
            $failed = count($report) - $successful;

            if (! $dryRun) {
                DB::transaction(function () use (&$report): void {
                    foreach ($report as &$row) {
                        if ($row['status'] !== 'valid') {
                            continue;
                        }
                        $service = Service::create($row['data']);
                        $row['status'] = 'imported';
                        $row['service_id'] = $service->id;
                    }
                    unset($row);
                });
            }

            $batch->forceFill([
                'status' => $dryRun ? 'preview' : 'completed',
                'successful_rows' => $successful,
                'failed_rows' => $failed,
                'report' => $report,
                'completed_at' => $dryRun ? null : now(),
            ])->save();

            return $batch->refresh();
        });
    }

    /** @return list<array<string, string>> */
    private function parse(string $contents): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($contents)) ?: [];
        if ($lines === []) {
            throw new InvalidArgumentException('The CSV file is empty.');
        }

        $headers = array_map(fn (string $header): string => strtolower(trim($header)), str_getcsv(preg_replace('/^\xEF\xBB\xBF/', '', array_shift($lines)) ?: ''));
        $required = ['customer_code', 'plan_slug', 'username'];
        if (array_diff($required, $headers) !== []) {
            throw new InvalidArgumentException('The CSV must include customer_code, plan_slug and username columns.');
        }

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line);
            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = trim((string) ($values[$index] ?? ''));
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** @param list<array<string, string>> $rows @return list<array{row: int, status: string, errors: list<string>, data: array<string, mixed>, service_id?: int}> */
    private function validateRows(array $rows): array
    {
        $customerCodes = array_values(array_filter(array_map(fn (array $row): string => trim($row['customer_code'] ?? ''), $rows)));
        $planSlugs = array_values(array_filter(array_map(fn (array $row): string => trim($row['plan_slug'] ?? ''), $rows)));
        $usernames = array_values(array_filter(array_map(fn (array $row): string => trim($row['username'] ?? ''), $rows)));
        $customers = Customer::query()->whereIn('code', $customerCodes)->pluck('id', 'code')->map(fn (mixed $id): int => (int) $id)->all();
        $plans = Plan::query()->whereIn('slug', $planSlugs)->pluck('id', 'slug')->map(fn (mixed $id): int => (int) $id)->all();
        $existingUsernames = Service::withTrashed()->whereIn('username', $usernames)->pluck('username')->all();
        $seenUsernames = [];
        $report = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $errors = [];
            $customerCode = trim($row['customer_code'] ?? '');
            $planSlug = trim($row['plan_slug'] ?? '');
            $username = trim($row['username'] ?? '');
            $status = strtolower(trim($row['status'] ?? 'pending'));
            $provisioningMode = strtolower(trim($row['provisioning_mode'] ?? 'manual'));
            $networkState = strtolower(trim($row['network_state'] ?? 'pending_sync'));
            $activatedAt = $this->date($row['activated_at'] ?? null, 'activated_at', $errors);
            $expiresAt = $this->date($row['expires_at'] ?? null, 'expires_at', $errors);

            if (! isset($customers[$customerCode])) {
                $errors[] = 'customer_code does not exist';
            }
            if (! isset($plans[$planSlug])) {
                $errors[] = 'plan_slug does not exist';
            }
            if (preg_match('/^[A-Za-z0-9._@:-]{1,64}$/', $username) !== 1) {
                $errors[] = 'username must contain only letters, numbers, dots, underscores, @, colons or hyphens';
            } elseif (in_array($username, $existingUsernames, true) || isset($seenUsernames[$username])) {
                $errors[] = 'username already exists';
            }
            if (! in_array($status, ['pending', 'active', 'suspended', 'terminated'], true)) {
                $errors[] = 'status is invalid';
            }
            if (! in_array($provisioningMode, ['manual', 'upstream_credential', 'mikrotik', 'radius', 'external'], true)) {
                $errors[] = 'provisioning_mode is invalid';
            }
            if (! in_array($networkState, ['unknown', 'pending_sync', 'in_sync', 'drifted', 'failed'], true)) {
                $errors[] = 'network_state is invalid';
            }
            if ($activatedAt !== null && $expiresAt !== null && $expiresAt->lessThanOrEqualTo($activatedAt)) {
                $errors[] = 'expires_at must be after activated_at';
            }

            $data = [
                'customer_id' => $customers[$customerCode] ?? 0,
                'plan_id' => $plans[$planSlug] ?? 0,
                'username' => $username,
                'password_encrypted' => filled($row['password'] ?? null) ? $row['password'] : null,
                'status' => $status,
                'provisioning_mode' => $provisioningMode,
                'network_state' => $networkState,
                'activated_at' => $activatedAt,
                'expires_at' => $expiresAt,
            ];
            if ($errors === []) {
                $seenUsernames[$username] = true;
            }
            $report[] = ['row' => $rowNumber, 'status' => $errors === [] ? 'valid' : 'rejected', 'errors' => $errors, 'data' => $data];
        }

        return $report;
    }

    /** @param list<string> $errors */
    private function date(?string $value, string $field, array &$errors): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            $errors[] = "{$field} is invalid";

            return null;
        }
    }
}
