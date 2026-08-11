<?php

use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Zone;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('lists due and overdue customers and returns collector-safe customer detail', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'collector-customers@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('collector');
    $zone = Zone::factory()->create(['code' => 'NORTH']);
    $due = Customer::factory()->create(['zone_id' => $zone->id, 'first_name' => 'Due']);
    $overdue = Customer::factory()->create(['zone_id' => $zone->id, 'first_name' => 'Overdue']);
    $current = Customer::factory()->create(['zone_id' => $zone->id, 'first_name' => 'Current']);
    $dueService = Service::factory()->for($due)->create(['status' => ServiceStatus::Active, 'expires_at' => now()->addDays(3)]);
    Service::factory()->for($overdue)->create(['status' => ServiceStatus::Active, 'expires_at' => now()->subDay()]);
    Service::factory()->for($current)->create(['status' => ServiceStatus::Active, 'expires_at' => now()->addDays(30)]);
    $token = $user->createToken('collector-customers', ['api', 'staff:collector'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/collector/customers?status=due,overdue&zone=NORTH')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.zone', 'NORTH');

    app(Tenancy::class)->set($tenant);
    $this->withToken($token)
        ->getJson('/api/v1/collector/customers/'.$due->public_id)
        ->assertOk()
        ->assertJsonPath('data.id', $due->public_id)
        ->assertJsonPath('data.name', $due->full_name)
        ->assertJsonPath('data.services.0.id', $dueService->public_id)
        ->assertJsonPath('data.services.0.days_remaining', 2);
});

it('resends a collector payment receipt through the selected channel', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'collector-receipt@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('collector');
    $customer = Customer::factory()->create();
    $payment = Payment::create(['customer_id' => $customer->id, 'number' => 'RCT-COLLECTOR-001', 'status' => 'posted', 'amount' => 300, 'currency' => 'USD', 'method' => 'cash', 'idempotency_key' => 'collector-receipt-payment-001', 'received_at' => now(), 'actor_id' => $user->id]);
    MessageTemplate::updateOrCreate(
        ['key' => 'payment.receipt', 'channel' => 'sms', 'locale' => 'en'],
        ['body' => 'Receipt {{ receipt_number }}'],
    );
    $token = $user->createToken('collector-receipt', ['api', 'staff:collector'])->plainTextToken;

    $response = $this->withToken($token)
        ->withHeader('X-Idempotency-Key', 'collector-receipt-resend-001')
        ->postJson('/api/v1/collector/payments/'.$payment->public_id.'/receipt', ['channel' => 'sms']);

    $response->assertStatus(202)->assertJsonPath('status', 'queued');
    app(Tenancy::class)->set($tenant);
    expect(Message::query()->where('template_key', 'payment.receipt')->count())->toBe(1)
        ->and(Message::query()->firstOrFail()->body)->toContain('RCT-COLLECTOR-001');
});
