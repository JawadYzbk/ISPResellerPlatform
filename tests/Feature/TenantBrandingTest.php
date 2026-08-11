<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('lets an owner upload a tenant logo for staff and portal branding', function (): void {
    Config::set('filesystems.default', 's3');
    $disk = 's3';
    Storage::fake($disk);
    $tenant = Tenant::create([
        'name' => 'Northline',
        'slug' => 'northline',
        'base_currency' => 'USD',
        'collection_currency' => 'LBP',
        'timezone' => 'Asia/Beirut',
    ]);
    $owner = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Maya Haddad',
        'email' => 'maya-branding@example.test',
        'password' => 'password',
        'role' => 'tenant_owner',
    ]);

    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $owner->assignRole('tenant_owner');

    $this->actingAs($owner)
        ->post(route('security.reauthenticate.store'), ['password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->actingAs($owner)
        ->put(route('settings.general.update'), [
            'name' => 'Northline',
            'locale' => 'en',
            'timezone' => 'Asia/Beirut',
            'base_currency' => 'USD',
            'collection_currency' => 'LBP',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'rtl' => false,
            'grace_extends_period' => false,
            'notification_quiet_start' => '22:00',
            'notification_quiet_end' => '07:00',
            'resolved_ticket_auto_close_hours' => 72,
            'radius_interim_interval_seconds' => 300,
            'logo' => UploadedFile::fake()->image('northline.png'),
        ])
        ->assertRedirect(route('settings.general'));

    $tenant->refresh();

    expect($tenant->logo_path)->toStartWith('tenants/'.$tenant->public_id.'/');
    Storage::disk($disk)->assertExists($tenant->logo_path);

    $this->get(route('tenant.logo', $tenant))->assertOk();
    $this->actingAs($owner)
        ->get(route('settings.general'))
        ->assertInertia(fn ($page) => $page->where('tenant.logo_url', route('tenant.logo', $tenant)));
    $this->get(route('portal.sign-in', $tenant))
        ->assertInertia(fn ($page) => $page->where('tenant.logo_url', route('tenant.logo', $tenant)));
});
