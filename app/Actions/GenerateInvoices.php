<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\ServiceStatus;
use App\Models\BillingRun;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

final readonly class GenerateInvoices implements Action
{
    public function __construct(private CreateInvoice $createInvoice, private IssueInvoice $issueInvoice) {}

    public function handle(Tenant $tenant, CarbonImmutable $period): BillingRun
    {
        return app(Tenancy::class)->run($tenant, fn (): BillingRun => $this->run($tenant, $period));
    }

    private function run(Tenant $tenant, CarbonImmutable $period): BillingRun
    {
        $run = BillingRun::firstOrCreate(
            ['run_type' => 'prepaid_renewal', 'period_key' => $period->toDateString()],
            ['status' => 'running', 'started_at' => now()],
        );
        if ($run->status === 'completed') {
            return $run;
        }

        $processed = 0;
        $failed = 0;
        $run->forceFill(['status' => 'running', 'started_at' => $run->started_at ?? now()])->save();
        $periodEnd = $period->endOfDay();
        Service::query()->with(['customer', 'plan'])->where('status', ServiceStatus::Active)->where('expires_at', '<=', $periodEnd)->chunkById(100, function ($services) use (&$processed, &$failed): void {
            foreach ($services as $service) {
                try {
                    $invoice = $this->createInvoice->handle($service->customer, $service->plan, $service, CarbonImmutable::now());
                    $this->issueInvoice->handle($invoice);
                    $processed++;
                } catch (\Throwable $exception) {
                    $failed++;
                    report($exception);
                }
            }
        });
        $run->forceFill(['status' => $failed === 0 ? 'completed' : 'completed_with_errors', 'processed_count' => $processed, 'failed_count' => $failed, 'completed_at' => now()])->save();

        return $run->refresh();
    }
}
