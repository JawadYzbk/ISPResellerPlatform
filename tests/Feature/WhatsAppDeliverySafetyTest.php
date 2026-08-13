<?php

use App\Actions\QueueMessage;
use App\Domain\Communications\WhatsAppWebMessageProvider;
use App\Enums\MessageStatus;
use App\Jobs\DeliverMessage;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\WhatsAppAccount;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('suppresses a recent semantic WhatsApp duplicate even with a new idempotency key', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $template = MessageTemplate::create([
        'key' => 'payment.receipt',
        'channel' => 'whatsapp',
        'locale' => 'en',
        'body' => 'Receipt {{ receipt_number }}',
    ]);

    $first = app(QueueMessage::class)->handle($template, '+961 70 123 456', 'whatsapp', 'en', 'receipt-001', ['receipt_number' => 'RCT-001']);
    $second = app(QueueMessage::class)->handle($template, '96170123456', 'whatsapp', 'en', 'receipt-002', ['receipt_number' => 'RCT-001']);

    expect($second->id)->toBe($first->id)
        ->and(Message::query()->count())->toBe(1);
    Queue::assertPushedTimes(DeliverMessage::class, 1);
});

it('paces a WhatsApp account before sending and records a failure cooldown', function (): void {
    config([
        'services.whatsapp.mode' => 'web',
        'services.whatsapp.web.enabled' => true,
        'services.whatsapp.web.endpoint' => 'http://whatsapp-web:3001',
        'services.whatsapp.web.token' => 'bridge-token',
        'services.whatsapp.safety.min_interval_seconds' => 8,
        'services.whatsapp.safety.jitter_seconds' => 0,
        'services.whatsapp.safety.failure_cooldown_seconds' => 30,
    ]);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $account = WhatsAppAccount::create([
        'label' => 'Billing',
        'job' => 'billing',
        'bridge_id' => 'billing-bridge',
        'status' => 'ready',
        'is_active' => true,
    ]);
    $message = Message::create([
        'channel' => 'whatsapp',
        'recipient' => '96170123456',
        'locale' => 'en',
        'body' => 'Receipt',
        'status' => MessageStatus::Queued,
        'idempotency_key' => 'safety-001',
        'whatsapp_account_id' => $account->id,
    ]);

    Http::fake(['http://whatsapp-web:3001/accounts/*/messages' => Http::response(['error' => 'busy'], 503)]);
    $first = app(WhatsAppWebMessageProvider::class)->send($message);
    expect($first->status)->toBe('failed')
        ->and($account->refresh()->failure_streak)->toBe(1)
        ->and($account->cooldown_until)->not->toBeNull();

    $second = app(WhatsAppWebMessageProvider::class)->send($message);
    expect($second->status)->toBe('deferred')
        ->and($second->metadata['whatsapp_safety_reason'])->toBe('account_pacing');
    Http::assertSentCount(1);
});

it('defers an account that has reached its hourly safety limit', function (): void {
    config([
        'services.whatsapp.mode' => 'web',
        'services.whatsapp.web.enabled' => true,
        'services.whatsapp.web.endpoint' => 'http://whatsapp-web:3001',
        'services.whatsapp.web.token' => 'bridge-token',
        'services.whatsapp.safety.hourly_limit' => 1,
    ]);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $account = WhatsAppAccount::create([
        'label' => 'Billing',
        'job' => 'billing',
        'bridge_id' => 'billing-bridge',
        'status' => 'ready',
        'is_active' => true,
    ]);
    Message::create([
        'channel' => 'whatsapp',
        'recipient' => '96170123456',
        'locale' => 'en',
        'body' => 'Already sent',
        'status' => MessageStatus::Sent,
        'idempotency_key' => 'safety-hourly-001',
        'whatsapp_account_id' => $account->id,
        'sent_at' => now()->subMinutes(5),
    ]);
    $message = Message::create([
        'channel' => 'whatsapp',
        'recipient' => '96170123457',
        'locale' => 'en',
        'body' => 'Waiting',
        'status' => MessageStatus::Queued,
        'idempotency_key' => 'safety-hourly-002',
        'whatsapp_account_id' => $account->id,
    ]);

    Http::fake();
    $result = app(WhatsAppWebMessageProvider::class)->send($message);

    expect($result->status)->toBe('deferred')
        ->and($result->metadata['whatsapp_safety_reason'])->toBe('hourly_account_limit');
    Http::assertNothingSent();
});
