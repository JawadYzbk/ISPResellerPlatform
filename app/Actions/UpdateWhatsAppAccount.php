<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\WhatsAppAccount;
use DomainException;

final readonly class UpdateWhatsAppAccount implements Action
{
    public function handle(WhatsAppAccount $account, string $label, string $job): WhatsAppAccount
    {
        $label = trim($label);
        $job = trim(strtolower($job));
        if ($label === '' || mb_strlen($label) > 80) {
            throw new DomainException('A WhatsApp account label is required and must be 80 characters or fewer.');
        }
        if (! in_array($job, WhatsAppAccount::JOBS, true)) {
            throw new DomainException('Choose a supported WhatsApp job assignment.');
        }

        $account->forceFill(['label' => $label, 'job' => $job])->save();

        return $account->refresh();
    }
}
