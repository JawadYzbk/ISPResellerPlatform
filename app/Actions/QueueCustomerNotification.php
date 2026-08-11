<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;

final readonly class QueueCustomerNotification implements Action
{
    /** @var list<string> */
    private const DEFAULT_CHANNELS = ['whatsapp', 'sms', 'email'];

    /** @var list<string> */
    private const SUPPORTED_CHANNELS = ['whatsapp', 'sms', 'email'];

    public function __construct(private QueueMessage $queueMessage) {}

    /**
     * @param  array<string, scalar|null>  $variables
     * @param  list<string>|null  $channels
     */
    public function handle(Customer $customer, string $templateKey, string $idempotencyKey, array $variables = [], ?array $channels = null): ?Message
    {
        $preferences = $customer->notification_preferences ?? [];
        if (($preferences['notifications'] ?? true) === false || ($preferences[$this->preferenceKey($templateKey)] ?? true) === false) {
            return null;
        }

        $locale = (string) (Tenant::query()->whereKey($customer->tenant_id)->value('locale') ?: 'en');
        $requestedChannels = $channels ?? ($preferences['channels'] ?? self::DEFAULT_CHANNELS);
        $selectedChannels = array_values(array_filter(
            is_array($requestedChannels) ? $requestedChannels : self::DEFAULT_CHANNELS,
            static fn (mixed $channel): bool => is_string($channel) && in_array($channel, self::SUPPORTED_CHANNELS, true),
        ));

        foreach ($selectedChannels as $channel) {
            $template = MessageTemplate::query()
                ->where('key', $templateKey)
                ->where('channel', $channel)
                ->where('locale', $locale)
                ->where('is_active', true)
                ->first();
            if ($template === null) {
                continue;
            }

            $recipient = $channel === 'email' ? $customer->email : $customer->phone;
            if (! is_string($recipient) || trim($recipient) === '') {
                continue;
            }

            $fallbackChannels = array_values(array_filter($selectedChannels, static fn (string $selected): bool => $selected !== $channel));

            return $this->queueMessage->handle($template, $recipient, $channel, $locale, $idempotencyKey, $variables, $customer, ['fallback_channels' => $fallbackChannels]);
        }

        return null;
    }

    private function preferenceKey(string $templateKey): string
    {
        return match ($templateKey) {
            'customer.welcome' => 'welcome_messages',
            'payment.receipt' => 'payment_receipts',
            'service.suspended', 'service.reactivated' => 'service_status_notifications',
            'service.expiry_reminder' => 'service_expiry_reminders',
            'outage.notice', 'outage.resolved' => 'outage_notifications',
            default => str_replace('.', '_', $templateKey),
        };
    }
}
