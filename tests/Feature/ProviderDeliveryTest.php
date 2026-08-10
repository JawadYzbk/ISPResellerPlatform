<?php

use App\Domain\Communications\FcmMessageProvider;
use App\Domain\Communications\HttpSmsMessageProvider;
use App\Domain\Communications\MailMessageProvider;
use App\Domain\Communications\WhatsAppCloudMessageProvider;
use App\Models\Message;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('delivers through configured WhatsApp, SMS, FCM and email adapters', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $whatsapp = Message::create(['channel' => 'whatsapp', 'recipient' => '96170123456', 'locale' => 'en', 'body' => 'Hello', 'status' => 'queued', 'idempotency_key' => 'provider-wa']);
    $sms = Message::create(['channel' => 'sms', 'recipient' => '96170123456', 'locale' => 'en', 'body' => 'Hello', 'status' => 'queued', 'idempotency_key' => 'provider-sms']);
    $push = Message::create(['channel' => 'push', 'recipient' => 'device-token', 'locale' => 'en', 'body' => 'Hello', 'status' => 'queued', 'idempotency_key' => 'provider-push']);
    $email = Message::create(['channel' => 'email', 'recipient' => 'customer@example.test', 'locale' => 'en', 'body' => 'Hello', 'subject' => 'Notice', 'status' => 'queued', 'idempotency_key' => 'provider-email']);
    config([
        'services.whatsapp.token' => 'wa-token',
        'services.whatsapp.phone_number_id' => 'phone-001',
        'services.sms.endpoint' => 'https://sms.example.test/send',
        'services.sms.token' => 'sms-token',
        'services.fcm.endpoint' => 'https://fcm.example.test/send',
        'services.fcm.token' => 'fcm-token',
        'services.notifications.email_enabled' => true,
    ]);
    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid-001']]]),
        'https://sms.example.test/send' => Http::response(['id' => 'sms-001']),
        'https://fcm.example.test/send' => Http::response(['name' => 'fcm-001']),
    ]);
    Mail::fake();

    expect(app(WhatsAppCloudMessageProvider::class)->send($whatsapp)->providerMessageId)->toBe('wamid-001')
        ->and(app(HttpSmsMessageProvider::class)->send($sms)->providerMessageId)->toBe('sms-001')
        ->and(app(FcmMessageProvider::class)->send($push)->providerMessageId)->toBe('fcm-001')
        ->and(app(MailMessageProvider::class)->send($email)->status)->toBe('sent');
    Mail::assertSent(fn ($mail): bool => $mail->hasTo('customer@example.test'));
});
