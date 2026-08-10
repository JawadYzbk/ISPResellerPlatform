<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use App\Models\Incident;
use App\Models\InventoryUnit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class SearchWorkspace implements Action
{
    /** @return list<array{type: string, label: string, detail: string, href: string}> */
    public function handle(User $user, string $search, int $perType = 5): array
    {
        $needle = trim($search);
        if ($needle === '') {
            return [];
        }

        $limit = min(max($perType, 1), 10);
        $like = '%'.$needle.'%';
        $results = [];

        if ($user->can('customers.view')) {
            Customer::query()
                ->where(fn (Builder $query) => $query->where('code', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('phone_normalized', 'like', $like))
                ->orderBy('last_name')
                ->limit($limit)
                ->get(['public_id', 'code', 'first_name', 'last_name', 'phone_normalized'])
                ->each(function (Customer $customer) use (&$results): void {
                    $results[] = ['type' => 'customer', 'label' => $customer->full_name, 'detail' => $customer->code.' · '.$customer->phone_normalized, 'href' => '/customers/'.$customer->public_id];
                });
        }

        if ($user->can('services.view')) {
            Service::query()
                ->with('customer')
                ->where(fn (Builder $query) => $query->where('username', 'like', $like)->orWhereHas('currentSessions', fn (Builder $session) => $session->where('framed_ip', 'like', $like))->orWhereHas('customer', fn (Builder $customer) => $customer->where('code', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)))
                ->orderBy('username')
                ->limit($limit)
                ->get(['public_id', 'username', 'customer_id'])
                ->each(function (Service $service) use (&$results): void {
                    $results[] = ['type' => 'service', 'label' => $service->username, 'detail' => $service->customer->full_name, 'href' => '/services/'.$service->public_id];
                });
        }

        if ($user->can('billing.invoices.view')) {
            Invoice::query()
                ->with('customer')
                ->where('number', 'like', $like)
                ->latest('issued_at')
                ->limit($limit)
                ->get(['public_id', 'number', 'customer_id', 'currency', 'total_amount'])
                ->each(function (Invoice $invoice) use (&$results): void {
                    $results[] = ['type' => 'invoice', 'label' => $invoice->number, 'detail' => $invoice->customer->full_name.' · '.$invoice->currency, 'href' => '/billing/invoices/'.$invoice->public_id];
                });
        }

        if ($user->can('payments.collect')) {
            Payment::query()
                ->with('customer')
                ->where('number', 'like', $like)
                ->latest('received_at')
                ->limit($limit)
                ->get(['public_id', 'number', 'customer_id', 'currency', 'amount'])
                ->each(function (Payment $payment) use (&$results): void {
                    $results[] = ['type' => 'payment', 'label' => $payment->number, 'detail' => $payment->customer->full_name.' · '.$payment->currency, 'href' => '/billing/payments/'.$payment->public_id];
                });
        }

        if ($user->can('tickets.view')) {
            Ticket::query()
                ->with('customer')
                ->where(fn (Builder $query) => $query->where('number', 'like', $like)->orWhere('subject', 'like', $like))
                ->latest('created_at')
                ->limit($limit)
                ->get(['public_id', 'number', 'subject', 'customer_id'])
                ->each(function (Ticket $ticket) use (&$results): void {
                    $results[] = ['type' => 'ticket', 'label' => $ticket->number.' · '.$ticket->subject, 'detail' => $ticket->customer->full_name, 'href' => '/operations/tickets/'.$ticket->public_id];
                });
        }

        if ($user->can('inventory.view')) {
            InventoryUnit::query()
                ->with(['item', 'service'])
                ->where('serial_number', 'like', $like)
                ->limit($limit)
                ->get(['serial_number', 'inventory_item_id', 'service_id'])
                ->each(function (InventoryUnit $unit) use (&$results): void {
                    $results[] = ['type' => 'equipment', 'label' => $unit->serial_number, 'detail' => $unit->item->name, 'href' => $unit->service === null ? '/operations/inventory' : '/services/'.$unit->service->public_id];
                });
        }

        if ($user->can('network.view')) {
            Incident::query()
                ->where(fn (Builder $query) => $query->where('title', 'like', $like)->orWhere('type', 'like', $like))
                ->latest('opened_at')
                ->limit($limit)
                ->get(['public_id', 'title', 'type', 'severity'])
                ->each(function (Incident $incident) use (&$results): void {
                    $results[] = ['type' => 'incident', 'label' => $incident->title, 'detail' => str_replace('_', ' ', $incident->type).' · '.$incident->severity, 'href' => '/operations/incidents/'.$incident->public_id];
                });
        }

        return $results;
    }
}
