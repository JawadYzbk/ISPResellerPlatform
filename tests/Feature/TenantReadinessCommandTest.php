<?php

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('reports a tenant readiness checklist and allows optional integration warnings', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'pilot-tenant']);
    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'tenant_owner',
    ]);

    app(Tenancy::class)->run($tenant, function () use ($owner): void {
        Role::findOrCreate('tenant_owner', 'web');
        $owner->assignRole('tenant_owner');

        $plan = Plan::create([
            'name' => 'Pilot Home',
            'slug' => 'pilot-home',
            'download_kbps' => 25_000,
            'upload_kbps' => 5_000,
            'duration_days' => 30,
            'amount_minor' => 2500,
            'currency' => 'USD',
            'status' => 'active',
        ]);
        $plan->prices()->create([
            'currency' => 'USD',
            'amount_minor' => 2500,
            'effective_from' => now()->subDay(),
        ]);
    });

    $this->artisan('platform:tenant-readiness', ['tenant' => $tenant->slug])
        ->assertExitCode(Command::SUCCESS)
        ->expectsOutputToContain('Tenant readiness passed with warnings.')
        ->expectsOutputToContain('Tenant logo');
});

it('fails when a tenant has no billable plan', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'incomplete-tenant']);

    $this->artisan('platform:tenant-readiness', ['tenant' => $tenant->slug])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('Billable plan')
        ->expectsOutputToContain('Tenant readiness failed.');
});
