<?php

namespace App\Console\Commands;

use App\Actions\GetTenantReadiness;
use App\Models\Tenant;
use Illuminate\Console\Command;

final class TenantReadinessCommand extends Command
{
    protected $signature = 'platform:tenant-readiness
        {tenant : Tenant slug or public ID}
        {--strict : Treat warnings as failures}
        {--json : Render machine-readable JSON}';

    protected $description = 'Check whether a tenant is ready for a supervised pilot handoff.';

    public function handle(GetTenantReadiness $readiness): int
    {
        $identifier = (string) $this->argument('tenant');
        $tenant = Tenant::query()
            ->where('slug', $identifier)
            ->orWhere('public_id', $identifier)
            ->first();

        if (! $tenant instanceof Tenant) {
            $this->error('Tenant not found: '.$identifier);

            return self::FAILURE;
        }

        $checks = $readiness->handle($tenant);
        $hasFailures = collect($checks)->contains(fn (array $check): bool => $check['status'] === 'FAIL');
        $hasWarnings = collect($checks)->contains(fn (array $check): bool => $check['status'] === 'WARN');
        $strict = (bool) $this->option('strict');
        $status = $hasFailures || ($strict && $hasWarnings)
            ? 'FAIL'
            : ($hasWarnings ? 'WARN' : 'PASS');

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'tenant' => [
                    'slug' => $tenant->slug,
                    'public_id' => $tenant->public_id,
                ],
                'status' => $status,
                'strict' => $strict,
                'checks' => $checks,
            ], JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Check', 'Result', 'Detail'],
                collect($checks)->map(fn (array $check, string $name): array => [
                    $name,
                    $check['status'],
                    $check['detail'],
                ])->all(),
            );

            match ($status) {
                'PASS' => $this->info('Tenant readiness passed.'),
                'WARN' => $this->warn('Tenant readiness passed with warnings.'),
                default => $this->error('Tenant readiness failed.'),
            };
        }

        return $status === 'FAIL' ? self::FAILURE : self::SUCCESS;
    }
}
