<?php

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
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

it('warns when the configured tenant logo is missing from storage', function (): void {
    Config::set('filesystems.default', 's3');
    Storage::fake('s3');

    $tenant = Tenant::factory()->create([
        'slug' => 'missing-logo-tenant',
        'logo_path' => 'tenants/missing-logo/logo.svg',
    ]);

    $this->artisan('platform:tenant-readiness', ['tenant' => $tenant->slug, '--json' => true])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('The tenant logo path is configured, but the stored file is missing from the s3 storage disk.');
});

it('passes the tenant logo check when the configured file exists', function (): void {
    Config::set('filesystems.default', 's3');
    Storage::fake('s3');
    Storage::disk('s3')->put('tenants/ready-logo/logo.svg', '<svg />');

    $tenant = Tenant::factory()->create([
        'slug' => 'ready-logo-tenant',
        'logo_path' => 'tenants/ready-logo/logo.svg',
    ]);

    $this->artisan('platform:tenant-readiness', ['tenant' => $tenant->slug, '--json' => true])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('A tenant logo is available on the configured storage disk.');
});
