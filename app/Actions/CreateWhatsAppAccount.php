<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Tenant;
use App\Models\WhatsAppAccount;
use DomainException;
use Illuminate\Support\Str;
use LogicException;

final readonly class CreateWhatsAppAccount implements Action
{
    public function __construct(private SynchronizeWhatsAppAccount $synchronize) {}

    public function handle(Tenant $tenant, string $label, string $job): WhatsAppAccount
    {
        $label = trim($label);
        $job = trim(strtolower($job));
        if ($label === '' || mb_strlen($label) > 80) {
            throw new DomainException('A WhatsApp account label is required and must be 80 characters or fewer.');
        }
        if (! in_array($job, WhatsAppAccount::JOBS, true)) {
            throw new DomainException('Choose a supported WhatsApp job assignment.');
        }

        $account = $tenant->whatsappAccounts()->create([
            'label' => $label,
            'job' => $job,
            'bridge_id' => 'wa-'.Str::lower((string) Str::ulid()),
            'status' => 'disconnected',
            'is_active' => true,
        ]);
        if (! $account instanceof WhatsAppAccount) {
            throw new LogicException('The WhatsApp account relation returned an unexpected model.');
        }

        $this->synchronize->handle($account);

        $refreshed = $account->refresh();
        if (! $refreshed instanceof WhatsAppAccount) {
            throw new LogicException('The WhatsApp account could not be refreshed.');
        }

        return $refreshed;
    }
}
