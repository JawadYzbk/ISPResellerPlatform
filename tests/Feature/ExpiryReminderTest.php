<?php

use App\Actions\QueueExpiryReminders;
use App\Enums\ServiceStatus;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('queues one tenant-local expiry reminder and respects opt-out and quiet hours', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'timezone' => 'Asia/Beirut', 'base_currency' => 'USD', 'collection_currency' => 'USD', 'settings' => ['expiry_reminder_send_hour' => 9]]);
    app(Tenancy::class)->set($tenant);
    $template = MessageTemplate::create(['key' => 'service.expiry_reminder', 'channel' => 'sms', 'locale' => 'en', 'body' => '{{ customer_name }} · {{ service_username }} expires {{ expiry_date }} ({{ days_remaining }} days).']);
    $localNow = CarbonImmutable::parse('2026-08-10 09:00:00', 'Asia/Beirut');
    $service = Service::factory()->create(['status' => ServiceStatus::Active, 'expires_at' => $localNow->addDays(7)->setTimezone('UTC')]);
    $optedOut = Service::factory()->create(['status' => ServiceStatus::Active, 'expires_at' => $localNow->addDays(7)->setTimezone('UTC')]);
    $optedOut->customer->forceFill(['notification_preferences' => ['service_expiry_reminders' => false]])->save();

    expect(app(QueueExpiryReminders::class)->handle($tenant, $localNow, 7))->toBe(1)
        ->and(app(QueueExpiryReminders::class)->handle($tenant, $localNow, 7))->toBe(0)
        ->and(app(QueueExpiryReminders::class)->handle($tenant, $localNow->setTime(22, 0), 7))->toBe(0)
        ->and(Message::count())->toBe(1);
});

it('uses the customer channel order so an expiry reminder can fall back', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Fallbackline', 'slug' => 'fallbackline', 'timezone' => 'Asia/Beirut', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    MessageTemplate::create(['key' => 'service.expiry_reminder', 'channel' => 'sms', 'locale' => 'en', 'body' => 'SMS {{ service_username }}']);
    MessageTemplate::create(['key' => 'service.expiry_reminder', 'channel' => 'email', 'locale' => 'en', 'body' => 'Email {{ service_username }}']);
    $localNow = CarbonImmutable::parse('2026-08-10 09:00:00', 'Asia/Beirut');
    $service = Service::factory()->create([
        'status' => ServiceStatus::Active,
        'expires_at' => $localNow->addDays(7)->setTimezone('UTC'),
    ]);
    $service->customer->forceFill(['notification_preferences' => ['channels' => ['sms', 'email']]])->save();

    expect(app(QueueExpiryReminders::class)->handle($tenant, $localNow, 7))->toBe(1);

    $message = Message::query()->firstOrFail();
    expect($message->channel)->toBe('sms')
        ->and($message->metadata['fallback_channels'])->toBe(['email']);
});
