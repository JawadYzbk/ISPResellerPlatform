<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MessageTemplateProvisioner;
use App\Support\Tenancy;
use DomainException;
use Illuminate\Support\Str;

final readonly class QueueWhatsAppTestMessage implements Action
{
    public function __construct(
        private QueueMessage $queueMessage,
        private MessageTemplateProvisioner $templateProvisioner,
    ) {}

    public function handle(Tenant $tenant, User $actor, string $recipient): Message
    {
        $normalizedRecipient = preg_replace('/\D+/', '', trim($recipient));
        if (! is_string($normalizedRecipient) || preg_match('/^\d{8,15}$/', $normalizedRecipient) !== 1) {
            throw new DomainException('Enter an international phone number with country code.');
        }

        return app(Tenancy::class)->run($tenant, function () use ($tenant, $actor, $normalizedRecipient): Message {
            $locale = (string) ($tenant->locale ?: 'en');
            $template = MessageTemplate::query()
                ->where('key', 'whatsapp.test')
                ->where('channel', 'whatsapp')
                ->where('locale', $locale)
                ->where('is_active', true)
                ->first();

            if ($template === null) {
                $this->templateProvisioner->provision($tenant, 'whatsapp.test', 'whatsapp', $locale);
                $template = MessageTemplate::query()
                    ->where('key', 'whatsapp.test')
                    ->where('channel', 'whatsapp')
                    ->where('locale', $locale)
                    ->where('is_active', true)
                    ->firstOrFail();
            }

            return $this->queueMessage->handle(
                $template,
                $normalizedRecipient,
                'whatsapp',
                $locale,
                'whatsapp-test:'.$actor->id.':'.Str::ulid(),
                ['tenant_name' => $tenant->name],
                metadata: ['test_notification' => true, 'actor_id' => $actor->id],
            );
        });
    }
}
