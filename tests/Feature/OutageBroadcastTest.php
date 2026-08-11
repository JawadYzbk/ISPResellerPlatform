<?php

use App\Actions\BroadcastIncidentNotification;
use App\Actions\CheckRouterHealth;
use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Incident;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Pop;
use App\Models\Router;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Zone;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('broadcasts router and POP outages once and sends recovery notices', function (): void {
    Queue::fake();
    Http::fakeSequence()->pushStatus(503)->pushStatus(503)->pushStatus(503)->push(['version' => '7.15.2', 'board-name' => 'CHR']);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $pop = Pop::create(['name' => 'Central POP', 'code' => 'CENTRAL']);
    $router = Router::create(['pop_id' => $pop->id, 'name' => 'Core', 'host' => 'router.example.test', 'username' => 'api', 'password_encrypted' => 'secret']);
    $customer = Customer::factory()->create();
    Service::factory()->create(['customer_id' => $customer->id, 'router_id' => $router->id, 'status' => ServiceStatus::Active]);
    MessageTemplate::updateOrCreate(
        ['key' => 'outage.notice', 'channel' => 'sms', 'locale' => 'en'],
        ['body' => 'Outage: {{ incident_title }}'],
    );
    MessageTemplate::updateOrCreate(
        ['key' => 'outage.resolved', 'channel' => 'sms', 'locale' => 'en'],
        ['body' => 'Resolved: {{ incident_title }}'],
    );

    expect(app(CheckRouterHealth::class)->handle($router, 3))->toBeNull()
        ->and(app(CheckRouterHealth::class)->handle($router, 3))->toBeNull();
    $incident = app(CheckRouterHealth::class)->handle($router, 3);

    expect($incident)->toBeInstanceOf(Incident::class)
        ->and(Message::query()->where('template_key', 'outage.notice')->count())->toBe(1)
        ->and(Message::query()->where('customer_id', $customer->id)->count())->toBe(1);

    app(CheckRouterHealth::class)->handle($router->refresh(), 3);

    expect(Message::query()->where('template_key', 'outage.resolved')->count())->toBe(1)
        ->and(Message::query()->where('customer_id', $customer->id)->count())->toBe(2);
});

it('targets a zone scope without notifying customers outside it', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $zone = Zone::factory()->create(['name' => 'North zone']);
    $inside = Customer::factory()->create(['zone_id' => $zone->id]);
    $outside = Customer::factory()->create();
    MessageTemplate::updateOrCreate(
        ['key' => 'outage.notice', 'channel' => 'sms', 'locale' => 'en'],
        ['body' => 'Outage: {{ incident_title }}'],
    );
    $incident = Incident::create(['type' => 'zone_outage', 'severity' => 'warning', 'status' => 'open', 'title' => 'Zone interruption', 'opened_at' => now(), 'metadata' => ['zone_id' => $zone->id]]);

    expect(app(BroadcastIncidentNotification::class)->handle($incident, 'outage.notice'))->toBe(1)
        ->and(Message::query()->where('customer_id', $inside->id)->count())->toBe(1)
        ->and(Message::query()->where('customer_id', $outside->id)->count())->toBe(0);
});
