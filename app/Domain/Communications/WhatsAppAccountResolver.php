<?php

namespace App\Domain\Communications;

use App\Models\Message;
use App\Models\WhatsAppAccount;

final readonly class WhatsAppAccountResolver
{
    public function resolve(Message $message): ?WhatsAppAccount
    {
        $message->loadMissing('whatsappAccount');
        $selected = $message->whatsappAccount;
        if ($selected instanceof WhatsAppAccount && $selected->is_active) {
            return $selected;
        }

        $job = $this->jobFor($message);
        $account = WhatsAppAccount::query()
            ->where('tenant_id', $message->tenant_id)
            ->where('is_active', true)
            ->where('job', $job)
            ->oldest('id')
            ->first();

        if ($account instanceof WhatsAppAccount) {
            return $account;
        }

        if ($job !== 'general') {
            $account = WhatsAppAccount::query()
                ->where('tenant_id', $message->tenant_id)
                ->where('is_active', true)
                ->where('job', 'general')
                ->oldest('id')
                ->first();
            if ($account instanceof WhatsAppAccount) {
                return $account;
            }
        }

        return null;
    }

    public function jobFor(Message $message): string
    {
        $requested = $message->metadata['whatsapp_job'] ?? null;
        if (is_string($requested) && in_array($requested, WhatsAppAccount::JOBS, true)) {
            return $requested;
        }

        return match (true) {
            str_starts_with($message->template_key, 'payment.') => 'billing',
            str_starts_with($message->template_key, 'collection.') => 'collections',
            str_starts_with($message->template_key, 'service.') => 'operations',
            str_starts_with($message->template_key, 'outage.'),
            str_starts_with($message->template_key, 'ticket.') => 'support',
            str_starts_with($message->template_key, 'marketing.') => 'marketing',
            default => 'general',
        };
    }
}
