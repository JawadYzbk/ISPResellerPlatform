<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('renders staff tickets and applies status and public reply workflows', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Support agent', 'email' => 'support@example.test', 'password' => Hash::make('password'), 'role' => 'support_agent']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('support_agent');
    $assignee = User::create(['tenant_id' => $tenant->id, 'name' => 'Field technician', 'email' => 'ticket-tech@example.test', 'password' => Hash::make('password'), 'role' => 'technician']);
    $assignee->assignRole('technician');
    $assigner = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations manager', 'email' => 'ticket-manager@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    $assigner->assignRole('operations_manager');
    $customer = Customer::factory()->create();
    $ticket = Ticket::create([
        'number' => 'TCK-00001',
        'customer_id' => $customer->id,
        'subject' => 'Connection drops',
        'description' => 'The connection drops every evening.',
        'priority' => 'high',
        'status' => 'open',
        'satisfaction_rating' => 5,
    ]);
    TicketMessage::create(['ticket_id' => $ticket->id, 'author_type' => 'customer', 'author_id' => $customer->id, 'body' => 'It started yesterday.', 'visibility' => 'public']);

    $this->actingAs($user)
        ->get(route('operations.tickets', ['status' => 'open', 'priority' => 'high']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/Tickets')
            ->where('tickets.data.0.number', 'TCK-00001')
            ->where('tickets.data.0.message_count', 1)
            ->where('filters.status', 'open')
            ->where('canMutate', true)
            ->where('canClose', true)
        );

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->get(route('operations.tickets.show', $ticket->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/TicketShow')
            ->where('ticket.number', 'TCK-00001')
            ->where('ticket.satisfaction_rating', 5)
            ->where('cannedResponses.0.title', 'Investigation in progress')
            ->where('ticket.messages.0.body', 'It started yesterday.')
        );

    app(Tenancy::class)->set($tenant);
    $this->actingAs($assigner)
        ->post(route('operations.tickets.assignee', $ticket->public_id), ['assignee_id' => $assignee->id])
        ->assertRedirect(route('operations.tickets.show', $ticket->public_id));
    app(Tenancy::class)->set($tenant);
    expect($ticket->refresh()->assigned_to)->toBe($assignee->id);

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->post(route('operations.tickets.status', $ticket->public_id), ['status' => 'in_progress'])
        ->assertRedirect(route('operations.tickets.show', $ticket->public_id));
    app(Tenancy::class)->set($tenant);
    expect($ticket->refresh()->status->value)->toBe('in_progress');

    $this->actingAs($user)
        ->post(route('operations.tickets.messages', $ticket->public_id), ['body' => 'We are checking the access node.'])
        ->assertRedirect(route('operations.tickets.show', $ticket->public_id));
    app(Tenancy::class)->set($tenant);
    expect(TicketMessage::query()->where('ticket_id', $ticket->id)->latest('id')->value('body'))->toBe('We are checking the access node.');

    $this->actingAs($user)
        ->post(route('operations.tickets.messages', $ticket->public_id), ['body' => 'Keep this for the next operator.', 'visibility' => 'internal'])
        ->assertRedirect(route('operations.tickets.show', $ticket->public_id));
    app(Tenancy::class)->set($tenant);
    expect(TicketMessage::query()->where('ticket_id', $ticket->id)->latest('id')->value('visibility'))->toBe('internal');
});

it('does not expose tickets from another tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Support agent', 'email' => 'support@example.test', 'password' => Hash::make('password'), 'role' => 'support_agent']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($otherTenant);
    $customer = Customer::factory()->create();
    $ticket = Ticket::create(['number' => 'TCK-SOUTH-001', 'customer_id' => $customer->id, 'subject' => 'South issue', 'description' => 'Private', 'priority' => 'normal', 'status' => 'open']);
    app(Tenancy::class)->set($tenant);
    $user->assignRole('support_agent');

    $this->actingAs($user)->get(route('operations.tickets'))->assertOk()->assertInertia(fn ($page) => $page->where('tickets.total', 0));
    $this->actingAs($user)->get(route('operations.tickets.show', $ticket->public_id))->assertNotFound();
});
