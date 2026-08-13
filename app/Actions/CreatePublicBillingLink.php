<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Data\CreatedPublicBillingLink;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PublicBillingLink;
use App\Models\User;
use DomainException;
use Illuminate\Support\Str;

final readonly class CreatePublicBillingLink implements Action
{
    public function handle(User $actor, string $type, Customer $customer, ?Invoice $invoice, ?Payment $payment, int $expiresInDays): CreatedPublicBillingLink
    {
        if (! $actor->can('billing.invoices.view') || (int) $actor->tenant_id !== (int) $customer->tenant_id) {
            throw new DomainException('You are not allowed to share billing documents for this customer.');
        }
        if (! in_array($type, PublicBillingLink::TYPES, true) || $expiresInDays < 1 || $expiresInDays > 90) {
            throw new DomainException('Choose a supported link type and expiry from 1 to 90 days.');
        }
        if (in_array($type, ['invoice', 'payment'], true) && ($invoice === null || (int) $invoice->customer_id !== (int) $customer->id)) {
            throw new DomainException('Choose an invoice belonging to this customer.');
        }
        if ($type === 'receipt' && ($payment === null || (int) $payment->customer_id !== (int) $customer->id)) {
            throw new DomainException('Choose a receipt belonging to this customer.');
        }
        if ($type === 'statement' && ($invoice !== null || $payment !== null)) {
            throw new DomainException('Statement links must target the customer account.');
        }

        $token = Str::random(64);
        $link = PublicBillingLink::create([
            'token_hash' => hash('sha256', $token),
            'type' => $type,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice?->id,
            'payment_id' => $payment?->id,
            'created_by_id' => $actor->id,
            'expires_at' => now()->addDays($expiresInDays),
        ]);

        return new CreatedPublicBillingLink($link, $token);
    }
}
