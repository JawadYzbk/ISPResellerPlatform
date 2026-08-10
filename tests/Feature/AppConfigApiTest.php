<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('returns the client version gate from the authenticated app config endpoint', function (): void {
    config()->set('app.min_supported_version', '2.0.0');
    config()->set('app.version', '2.1.0');
    $tenant = Tenant::factory()->create(['name' => 'Northline', 'slug' => 'northline']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Staff', 'email' => 'app-config@example.test', 'password' => Hash::make('password'), 'role' => 'support_agent']);
    $token = $user->createToken('mobile', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->withHeader('X-Client', 'mobile/1.9.4')->getJson('/api/v1/app/config')
        ->assertOk()
        ->assertJsonPath('min_supported_version', '2.0.0')
        ->assertJsonPath('latest_version', '2.1.0')
        ->assertJsonPath('force_update', true);
});
