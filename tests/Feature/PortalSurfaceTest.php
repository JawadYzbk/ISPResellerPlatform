<?php

use App\Actions\RequestPortalOtp;
use App\Actions\VerifyPortalOtp;
use App\Enums\IncidentStatus;
use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Incident;
use App\Models\Plan;
use App\Models\Router;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\UsageDaily;
use App\Models\Zone;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves an ownership-safe portal profile, services, usage and notices surface', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $zone = Zone::factory()->create(['name' => 'North zone']);
    $router = Router::create(['name' => 'Core', 'host' => 'router.example.test', 'username' => 'api', 'password_encrypted' => 'secret']);
    $customer = Customer::factory()->create(['zone_id' => $zone->id, 'phone' => '+96170456789']);
    $plan = Plan::factory()->create(['name' => 'Home 50']);
    $service = Service::factory()->create(['customer_id' => $customer->id, 'plan_id' => $plan->id, 'router_id' => $router->id, 'status' => ServiceStatus::Active]);
    UsageDaily::create(['service_id' => $service->id, 'usage_date' => now()->subDay()->toDateString(), 'input_octets' => 100, 'output_octets' => 200, 'total_octets' => 300, 'rolled_up_at' => now()]);
    Incident::create(['type' => 'zone_outage', 'severity' => 'warning', 'status' => IncidentStatus::Open, 'title' => 'North interruption', 'description' => 'Maintenance in progress.', 'opened_at' => now(), 'metadata' => ['zone_id' => $zone->id]]);

    $result = app(RequestPortalOtp::class)->handle($tenant, $customer->phone);
    $session = app(VerifyPortalOtp::class)->handle($tenant, $result['challenge']->public_id, $result['code']);
    $headers = ['Authorization' => 'Bearer '.$session['token']];

    $this->withHeaders($headers)->getJson('/api/v1/portal/northline/me')
        ->assertOk()
        ->assertJsonPath('public_id', $customer->public_id)
        ->assertJsonPath('services.0.public_id', $service->public_id)
        ->assertJsonPath('services.0.uuid', $service->public_id)
        ->assertJsonMissingPath('id');
    $this->withHeaders($headers)->getJson('/api/v1/portal/northline/me/services')
        ->assertOk()
        ->assertJsonPath('data.0.uuid', $service->public_id);
    $this->withHeaders($headers)->getJson('/api/v1/portal/northline/me/services/'.$service->public_id.'/usage')
        ->assertOk()
        ->assertJsonPath('data.0.total_octets', 300);
    $this->withHeaders($headers)->getJson('/api/v1/portal/northline/me/notices')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'North interruption');
    $this->withHeaders($headers)->patchJson('/api/v1/portal/northline/me/profile', ['email' => 'customer@example.test', 'address' => 'New address'])
        ->assertOk()
        ->assertJsonPath('data.email', 'customer@example.test')
        ->assertJsonPath('data.address', 'New address')
        ->assertJsonMissingPath('data.id');
});

it('does not allow a portal customer to read another customer service usage', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['phone' => '+96170111111']);
    $other = Customer::factory()->create(['phone' => '+96170222222']);
    $service = Service::factory()->create(['customer_id' => $other->id]);
    $result = app(RequestPortalOtp::class)->handle($tenant, $customer->phone);
    $session = app(VerifyPortalOtp::class)->handle($tenant, $result['challenge']->public_id, $result['code']);

    $this->withToken($session['token'])->getJson('/api/v1/portal/southline/me/services/'.$service->public_id.'/usage')->assertNotFound();
});
