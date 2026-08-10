<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Invoice;

final readonly class GetInvoiceDetails implements Action
{
    public function handle(Invoice $invoice): Invoice
    {
        return $invoice->load([
            'customer',
            'lines.plan',
            'lines.service',
            'payments' => fn ($query) => $query->where('status', 'posted')->with(['actor', 'allocations']),
            'creditNotes' => fn ($query) => $query->where('status', 'issued')->with('creator'),
        ]);
    }
}
