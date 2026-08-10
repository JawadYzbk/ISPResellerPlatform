<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lists, creates, replies to, and changes ticket status through the operator API', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Support', 'email' => 'ticket-api@example.test', 'password' => Hash::make('password'), 'role' => 'support_agent']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('support_agent');
    $customer = Customer::factory()->create(['first_name' => 'Maya']);
    $token = $user->createToken('ticket-api', ['api', 'staff:operator'])->plainTextToken;

    $created = $this->withToken($token)->postJson('/api/v1/tickets', [
        'customer_id' => $customer->public_id,
        'subject' => 'Connection issue',
        'description' => 'The connection drops every evening.',
        'category' => 'connectivity',
        'priority' => 'high',
    ])->assertCreated()
        ->assertJsonPath('status', 'open')
        ->assertJsonPath('customer.id', $customer->public_id)
        ->json('id');

    $this->withToken($token)->getJson('/api/v1/tickets?filter[status]=open&filter[search]=Maya')
        ->assertOk()
        ->assertJsonPath('data.0.id', $created);

    $this->withToken($token)->postJson('/api/v1/tickets/'.$created.'/messages', [
        'body' => 'We are investigating the connection drops.',
        'visibility' => 'public',
    ])->assertOk()
        ->assertJsonCount(2, 'messages');

    $this->withToken($token)->postJson('/api/v1/tickets/'.$created.'/status', ['status' => 'in_progress'])
        ->assertOk()
        ->assertJsonPath('status', 'in_progress');

    expect(Ticket::withoutGlobalScopes()->where('public_id', $created)->firstOrFail()->status->value)->toBe('in_progress');
});

it('does not expose tickets to staff without ticket access', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Billing', 'email' => 'ticket-reader@example.test', 'password' => Hash::make('password'), 'role' => 'billing_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('billing_manager');
    $token = $user->createToken('ticket-reader-api', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/tickets')->assertForbidden();
});
