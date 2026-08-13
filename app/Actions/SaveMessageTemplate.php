<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\MessageTemplate;

final readonly class SaveMessageTemplate implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(MessageTemplate $template, array $data): MessageTemplate
    {
        $template->forceFill([
            'subject' => filled($data['subject'] ?? null) ? trim((string) $data['subject']) : null,
            'body' => trim((string) $data['body']),
        ])->save();

        return $template->refresh();
    }
}
