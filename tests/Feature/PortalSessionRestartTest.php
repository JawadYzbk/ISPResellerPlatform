<?php

use App\Actions\RequestPortalOtp;
use App\Actions\VerifyPortalOtp;
use App\Enums\ServiceStatus;
use App\Models\CurrentSession;
use App\Models\Customer;
use App\Models\NetworkCommand;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('queues an idempotent customer session restart with the live session id', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['phone' => '+96170456789']);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'status' => ServiceStatus::Active]);
    CurrentSession::create(['service_id' => $service->id, 'username' => $service->username, 'acct_session_id' => 'portal-session-001', 'nasname' => 'router-01', 'last_seen_at' => now()]);
    $otp = app(RequestPortalOtp::class)->handle($tenant, '+961 70 456 789');
    $session = app(VerifyPortalOtp::class)->handle($tenant, $otp['challenge']->public_id, $otp['code']);

    $headers = ['X-Idempotency-Key' => 'portal-restart-001'];
    $first = $this->withToken($session['token'])->withHeaders($headers)->postJson('/api/v1/portal/northline/me/services/'.$service->public_id.'/restart-session');
    $second = $this->withToken($session['token'])->withHeaders($headers)->postJson('/api/v1/portal/northline/me/services/'.$service->public_id.'/restart-session');

    $first->assertStatus(202)->assertJsonPath('status', 'restart_queued')->assertJsonPath('session_id', 'portal-session-001');
    $second->assertStatus(202)->assertJsonPath('command_id', $first->json('command_id'));
    app(Tenancy::class)->set($tenant);
    expect(NetworkCommand::query()->where('service_id', $service->id)->where('action', 'disconnect')->count())->toBe(1)
        ->and(NetworkCommand::query()->firstOrFail()->payload['reason'])->toBe('customer_session_restart');
});

it('rate limits a second customer session restart request', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['phone' => '+96170456789']);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'status' => ServiceStatus::Active]);
    $otp = app(RequestPortalOtp::class)->handle($tenant, '+961 70 456 789');
    $session = app(VerifyPortalOtp::class)->handle($tenant, $otp['challenge']->public_id, $otp['code']);

    $this->withToken($session['token'])->withHeader('X-Idempotency-Key', 'portal-restart-002')->postJson('/api/v1/portal/southline/me/services/'.$service->public_id.'/restart-session')->assertStatus(202);
    $this->withToken($session['token'])->withHeader('X-Idempotency-Key', 'portal-restart-003')->postJson('/api/v1/portal/southline/me/services/'.$service->public_id.'/restart-session')->assertStatus(429)->assertJsonPath('message', 'A session restart can be requested once every five minutes.');
});
