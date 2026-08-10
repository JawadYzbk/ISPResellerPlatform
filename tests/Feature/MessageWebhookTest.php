<?php

use App\Models\Message;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('authenticates and idempotently applies a provider delivery callback', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    config(['services.webhooks.secrets.sms' => 'webhook-secret']);
    $message = Message::create(['channel' => 'sms', 'recipient' => '96170123456', 'locale' => 'en', 'body' => 'Hello', 'status' => 'sent', 'provider' => 'sms', 'provider_message_id' => 'sms-001', 'idempotency_key' => 'webhook-message-001']);
    $payload = ['id' => 'sms-001', 'status' => 'delivered'];
    $signature = hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), 'webhook-secret');

    $first = $this->withHeaders(['X-Webhook-Signature' => $signature])->postJson('/api/v1/webhooks/gateways/sms', $payload);
    $second = $this->withHeaders(['X-Webhook-Signature' => $signature])->postJson('/api/v1/webhooks/gateways/sms', $payload);

    $first->assertOk()->assertJsonPath('status', 'processed');
    $second->assertOk()->assertJsonPath('status', 'processed');
    expect($message->refresh()->status->value)->toBe('delivered');

    $this->withHeaders(['X-Webhook-Signature' => 'invalid'])->postJson('/api/v1/webhooks/gateways/sms', $payload)->assertUnauthorized();
});
