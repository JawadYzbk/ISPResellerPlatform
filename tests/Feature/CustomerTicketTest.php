<?php

use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('opens a staff ticket from a customer context and records the actor trail', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Support', 'email' => 'customer-ticket@example.test', 'password' => Hash::make('password'), 'role' => 'support_agent']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('support_agent');
    $customer = Customer::factory()->create();

    $this->actingAs($user)
        ->get(route('operations.tickets.create', $customer->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Operations/TicketCreate')->where('customer.public_id', $customer->public_id));

    $this->actingAs($user)
        ->post(route('operations.tickets.store', $customer->public_id), [
            'subject' => 'Customer reports intermittent service',
            'description' => 'The customer sees repeated drops during the evening.',
            'category' => 'connection',
            'priority' => 'high',
        ])
        ->assertRedirect();

    app(Tenancy::class)->set($tenant);
    $ticket = Ticket::query()->latest('id')->firstOrFail();
    expect($ticket->customer_id)->toBe($customer->id)
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->messages()->firstOrFail()->author_id)->toBe($user->id)
        ->and($ticket->events()->firstOrFail()->actor_id)->toBe($user->id);
});
