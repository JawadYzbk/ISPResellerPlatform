<?php

use App\Jobs\TenantAwareJob;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

final class ContextProbeJob extends TenantAwareJob
{
    public function handle(): int
    {
        return app(Tenancy::class)->requireId();
    }
}

it('restores and clears tenant context around queued work', function (): void {
    $tenant = Tenant::create(['name' => 'Probe', 'slug' => 'probe', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $job = new ContextProbeJob($tenant->id);
    $middleware = $job->middleware()[0];
    $observedTenantId = null;

    $middleware->handle($job, function (ContextProbeJob $job) use (&$observedTenantId): void {
        $observedTenantId = $job->handle();
    });

    expect($observedTenantId)->toBe($tenant->id)
        ->and(app(Tenancy::class)->id())->toBeNull();
});
