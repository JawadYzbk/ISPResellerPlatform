<?php

use App\Actions\RequestPortalOtp;
use App\Actions\VerifyPortalOtp;
use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates, lists and replies to a customer-owned portal ticket', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['phone' => '+96170333333']);
    $service = Service::factory()->create(['customer_id' => $customer->id, 'status' => ServiceStatus::Active]);
    $result = app(RequestPortalOtp::class)->handle($tenant, $customer->phone);
    $session = app(VerifyPortalOtp::class)->handle($tenant, $result['challenge']->public_id, $result['code']);
    $headers = ['Authorization' => 'Bearer '.$session['token']];

    $created = $this->withHeaders($headers)->postJson('/api/v1/portal/northline/me/tickets', [
        'category' => 'slow',
        'subject' => 'Slow connection',
        'description' => 'The connection slows down every evening.',
        'service_uuid' => $service->public_id,
    ])->assertCreated();
    $ticketUuid = $created->json('data.uuid');

    app(Tenancy::class)->set($tenant);
    expect(Ticket::query()->count())->toBe(1);
    $this->withHeaders($headers)->getJson('/api/v1/portal/northline/me/tickets')->assertOk()->assertJsonPath('data.0.uuid', $ticketUuid);
    $this->withHeaders($headers)->getJson('/api/v1/portal/northline/me/tickets/'.$ticketUuid)
        ->assertOk()
        ->assertJsonPath('data.messages.0.body', 'The connection slows down every evening.');
    $this->withHeaders($headers)->postJson('/api/v1/portal/northline/me/tickets/'.$ticketUuid.'/messages', ['body' => 'It is still slow today.'])
        ->assertOk()
        ->assertJsonPath('data.messages.1.body', 'It is still slow today.');
});

it('does not disclose another customer ticket through the portal', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['phone' => '+96170444444']);
    $other = Customer::factory()->create(['phone' => '+96170555555']);
    $ticket = Ticket::create(['customer_id' => $other->id, 'number' => 'TCK-OTHER', 'subject' => 'Private', 'description' => 'Private ticket', 'status' => 'open']);
    $result = app(RequestPortalOtp::class)->handle($tenant, $customer->phone);
    $session = app(VerifyPortalOtp::class)->handle($tenant, $result['challenge']->public_id, $result['code']);

    $this->withToken($session['token'])->getJson('/api/v1/portal/southline/me/tickets/'.$ticket->public_id)->assertNotFound();
});
