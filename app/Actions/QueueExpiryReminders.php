<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\ServiceStatus;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

final readonly class QueueExpiryReminders implements Action
{
    public function __construct(private QueueMessage $queueMessage) {}

    public function handle(Tenant $tenant, ?CarbonImmutable $at = null, int $offsetDays = 7): int
    {
        return app(Tenancy::class)->run($tenant, function () use ($tenant, $at, $offsetDays): int {
            $now = ($at ?? CarbonImmutable::now())->setTimezone($tenant->timezone ?: 'UTC');
            $settings = $tenant->settingsData()->settings;
            if ($this->isQuiet($now, (string) ($settings['notification_quiet_start'] ?? '21:00'), (string) ($settings['notification_quiet_end'] ?? '08:00')) || $now->hour !== (int) ($settings['expiry_reminder_send_hour'] ?? 9)) {
                return 0;
            }
            $template = MessageTemplate::query()->where('key', 'service.expiry_reminder')->where('channel', 'sms')->where('locale', $tenant->locale ?: 'en')->where('is_active', true)->first();
            if ($template === null) {
                return 0;
            }

            $target = $now->addDays(max(1, $offsetDays));
            $start = $target->startOfDay()->setTimezone('UTC');
            $end = $target->endOfDay()->setTimezone('UTC');
            $queued = 0;
            Service::query()->with('customer')->where('status', ServiceStatus::Active)->whereBetween('expires_at', [$start, $end])->chunkById(100, function ($services) use (&$queued, $template, $offsetDays, $target): void {
                foreach ($services as $service) {
                    $customer = $service->customer;
                    if (($customer->notification_preferences['service_expiry_reminders'] ?? true) === false) {
                        continue;
                    }
                    $key = 'expiry-reminder:'.$service->id.':'.$target->toDateString().':'.$offsetDays;
                    if (Message::query()->where('idempotency_key', $key)->exists()) {
                        continue;
                    }
                    $this->queueMessage->handle($template, $customer->phone, 'sms', $template->locale, $key, [
                        'customer_name' => $customer->full_name,
                        'service_username' => $service->username,
                        'expiry_date' => $target->toDateString(),
                        'days_remaining' => $offsetDays,
                    ], $customer);
                    $queued++;
                }
            });

            return $queued;
        });
    }

    private function isQuiet(CarbonImmutable $at, string $start, string $end): bool
    {
        $minutes = $at->hour * 60 + $at->minute;
        [$startHour, $startMinute] = array_map('intval', array_pad(explode(':', $start), 2, 0));
        [$endHour, $endMinute] = array_map('intval', array_pad(explode(':', $end), 2, 0));
        $startMinutes = $startHour * 60 + $startMinute;
        $endMinutes = $endHour * 60 + $endMinute;

        return $startMinutes < $endMinutes
            ? $minutes >= $startMinutes && $minutes < $endMinutes
            : $minutes >= $startMinutes || $minutes < $endMinutes;
    }
}
