<?php

use App\Models\CurrentSession;
use App\Models\NetworkCommand;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\UsageDaily;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('returns tenant-scoped service diagnostics for a technician', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Tech', 'email' => 'diagnostics-tech@example.test', 'password' => Hash::make('password'), 'role' => 'technician']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('technician');
    $service = Service::factory()->create(['username' => 'ada.home']);
    CurrentSession::create(['service_id' => $service->id, 'username' => $service->username, 'acct_session_id' => 'diag-session-001', 'nasname' => 'router-01', 'last_seen_at' => now(), 'input_octets' => 100, 'output_octets' => 250]);
    UsageDaily::create(['service_id' => $service->id, 'usage_date' => now()->toDateString(), 'input_octets' => 100, 'output_octets' => 250, 'total_octets' => 350, 'rolled_up_at' => now()]);
    NetworkCommand::create(['service_id' => $service->id, 'action' => 'activate', 'status' => 'failed', 'desired_state_version' => 1, 'attempts' => 2, 'last_error' => 'router unreachable']);
    $token = $user->createToken('technician', ['api', 'staff:technician'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/technician/services/'.$service->public_id.'/diagnostics')
        ->assertOk()
        ->assertJsonPath('service.username', 'ada.home')
        ->assertJsonPath('live_session.acct_session_id', 'diag-session-001')
        ->assertJsonPath('live_session.input_octets', 100)
        ->assertJsonPath('usage_last_24h.0.total_octets', 350)
        ->assertJsonPath('recent_commands.0.status', 'failed');
});
