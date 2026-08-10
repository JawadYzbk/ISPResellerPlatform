<?php

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CollectorSyncToken;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function collectorSyncToken(Tenant $tenant, string $email = 'sync-collector@example.test'): string
{
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => $email, 'password' => Hash::make('password'), 'role' => 'collector']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('collector');

    return $user->createToken('collector-device', ['api', 'staff:collector'])->plainTextToken;
}

it('returns a signed collector bootstrap snapshot without service secrets', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $token = collectorSyncToken($tenant);
    $customer = Customer::factory()->create(['first_name' => 'Rami']);

    $response = $this->withToken($token)->getJson('/api/v1/collector/sync/bootstrap');

    $response->assertOk()->assertJsonStructure(['sync_token', 'generated_at', 'data' => ['customers', 'services', 'plans', 'exchange_rates', 'message_templates'], 'tombstones']);
    expect($response->json('data.customers.0.id'))->toBe($customer->public_id)
        ->and($response->json('data.customers.0.first_name'))->toBe('Rami')
        ->and($response->json('data.services'))->toBeArray();
});

it('returns changed customer rows from a valid delta token', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $token = collectorSyncToken($tenant, 'sync-delta@example.test');
    $customer = Customer::factory()->create(['first_name' => 'Before']);
    $bootstrap = $this->withToken($token)->getJson('/api/v1/collector/sync/bootstrap')->assertOk();
    app(Tenancy::class)->set($tenant);
    $customer->update(['first_name' => 'After']);

    $response = $this->withToken($token)->getJson('/api/v1/collector/sync/delta?since='.urlencode($bootstrap->json('sync_token')));

    $response->assertOk()->assertJsonPath('since', $bootstrap->json('sync_token'));
    expect($response->json('data.customers'))->toHaveCount(1)
        ->and($response->json('data.customers.0.first_name'))->toBe('After');
});

it('pushes queued payments with created, replayed and rejected item results', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $token = collectorSyncToken($tenant, 'sync-push@example.test');
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $items = [
        ['customer_uuid' => $customer->public_id, 'amount' => 100, 'currency' => 'USD', 'method' => 'cash', 'idempotency_key' => 'sync-payment-001'],
        ['customer_uuid' => $customer->public_id, 'amount' => 100, 'currency' => 'USD', 'method' => 'cash', 'idempotency_key' => 'sync-payment-001'],
        ['customer_uuid' => $customer->public_id, 'amount' => 100, 'currency' => 'EUR', 'method' => 'cash', 'idempotency_key' => 'sync-payment-002'],
    ];

    $response = $this->withToken($token)->withHeader('X-Idempotency-Key', 'sync-push-request-001')->postJson('/api/v1/collector/sync/push', $items);

    $response->assertOk()->assertJsonCount(3, 'results');
    expect($response->json('results.0.status'))->toBe('created')
        ->and($response->json('results.1.status'))->toBe('replayed')
        ->and($response->json('results.2.status'))->toBe('rejected')
        ->and(Payment::withoutGlobalScopes()->count())->toBe(1);
});

it('rejects a collector delta token issued for another user', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $firstToken = collectorSyncToken($tenant, 'sync-first@example.test');
    $bootstrap = $this->withToken($firstToken)->getJson('/api/v1/collector/sync/bootstrap')->assertOk();
    $secondToken = collectorSyncToken($tenant, 'sync-second@example.test');
    $firstId = User::query()->where('email', 'sync-first@example.test')->value('id');
    $secondId = User::query()->where('email', 'sync-second@example.test')->value('id');
    app('auth')->forgetGuards();
    expect($firstId)->not->toBe($secondId)
        ->and($secondToken)->not->toBe($firstToken)
        ->and($this->flushHeaders()->withToken($secondToken)->getJson('/api/v1/me')->json('email'))->toBe('sync-second@example.test')
        ->and(fn () => app(CollectorSyncToken::class)->read($bootstrap->json('sync_token'), $tenant->id, $secondId))->toThrow(InvalidArgumentException::class);

    $response = $this->flushHeaders()->withToken($secondToken)->getJson('/api/v1/collector/sync/delta?since='.urlencode($bootstrap->json('sync_token')));

    $response->assertStatus(422)->assertJsonPath('message', 'The sync token is invalid.');
});
