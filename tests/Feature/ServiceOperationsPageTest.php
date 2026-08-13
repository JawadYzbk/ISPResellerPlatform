<?php

use App\Enums\ServiceStatus;
use App\Models\CurrentSession;
use App\Models\Customer;
use App\Models\NetworkCommand;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\UsageDaily;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('renders the tenant service queue with server-side status filters', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'services@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create();
    $active = Service::factory()->create(['customer_id' => $customer->id, 'plan_id' => $plan->id, 'status' => ServiceStatus::Active, 'username' => 'active-user']);
    Service::factory()->create(['customer_id' => $customer->id, 'plan_id' => $plan->id, 'status' => ServiceStatus::Suspended, 'username' => 'suspended-user']);

    $this->actingAs($user)
        ->get(route('services.index', ['status' => 'active']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Services/Index')
            ->where('filters.status', 'active')
            ->where('services.total', 1)
            ->where('services.data.0.public_id', $active->public_id)
        );
});

it('renders service diagnostics, current session, usage, and command history', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline-detail', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'service-detail@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $service = Service::factory()->create(['status' => ServiceStatus::Active]);
    CurrentSession::create(['service_id' => $service->id, 'username' => $service->username, 'acct_session_id' => 'detail-session-001', 'nasname' => 'router-01', 'last_seen_at' => now()]);
    UsageDaily::create(['service_id' => $service->id, 'usage_date' => now()->toDateString(), 'input_octets' => 100, 'output_octets' => 200, 'total_octets' => 300, 'rolled_up_at' => now()]);
    NetworkCommand::create(['service_id' => $service->id, 'action' => 'activate', 'status' => 'completed', 'desired_state_version' => 1, 'completed_at' => now()]);

    $this->actingAs($user)
        ->get(route('services.show', $service->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Services/Show')
            ->where('service.username', $service->username)
            ->where('liveSession.acct_session_id', 'detail-session-001')
            ->where('usageLast24h.0.total_octets', 300)
            ->where('usageHistory.0.total_octets', 300)
            ->where('recentCommands.0.action', 'activate')
            ->where('canDisconnectSession', true)
        );
});
