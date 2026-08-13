<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CollectorTask;
use App\Models\Customer;
use App\Models\Incident;
use App\Models\InventoryUnit;
use App\Models\Invoice;
use App\Models\IpPool;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Pop;
use App\Models\Router;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\WorkspacePageCatalog;
use Illuminate\Database\Eloquent\Builder;

final readonly class SearchWorkspace implements Action
{
    public function __construct(private WorkspacePageCatalog $pages) {}

    /** @return list<array{type: string, label: string, detail: string, href: string, localized?: bool}> */
    public function handle(User $user, string $search, int $perType = 5): array
    {
        $needle = trim($search);
        if ($needle === '') {
            return [];
        }

        $limit = min(max($perType, 1), 10);
        $like = '%'.$needle.'%';
        $results = $this->pageResults($user, $needle);

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

        if ($user->can('plans.manage')) {
            Plan::query()
                ->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('slug', 'like', $like))
                ->orderBy('name')
                ->limit($limit)
                ->get(['public_id', 'name', 'slug', 'currency', 'status'])
                ->each(function (Plan $plan) use (&$results): void {
                    $results[] = ['type' => 'plan', 'label' => $plan->name, 'detail' => $plan->currency.' · '.$plan->status, 'href' => '/plans/'.$plan->public_id.'/edit'];
                });
        }

        if ($user->can('workorders.complete')) {
            WorkOrder::query()
                ->with('customer')
                ->where(fn (Builder $query) => $query
                    ->where('number', 'like', $like)
                    ->orWhere('type', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhereHas('customer', fn (Builder $customer) => $customer
                        ->where('code', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)))
                ->latest('created_at')
                ->limit($limit)
                ->get(['public_id', 'number', 'type', 'status', 'customer_id'])
                ->each(function (WorkOrder $workOrder) use (&$results): void {
                    $status = $workOrder->status?->value ?? (string) $workOrder->status;
                    $results[] = ['type' => 'work order', 'label' => $workOrder->number, 'detail' => $workOrder->customer->full_name.' · '.$status, 'href' => '/operations/work-orders/'.$workOrder->public_id];
                });
        }

        if ($user->can('network.view')) {
            Router::query()
                ->with('pop')
                ->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('host', 'like', $like)->orWhere('status', 'like', $like))
                ->orderBy('name')
                ->limit($limit)
                ->get(['public_id', 'name', 'host', 'status', 'pop_id'])
                ->each(function (Router $router) use (&$results): void {
                    $pop = $router->pop?->name;
                    $results[] = ['type' => 'router', 'label' => $router->name, 'detail' => $router->host.' · '.($pop ?: $router->status), 'href' => '/operations/routers/'.$router->public_id];
                });

            Pop::query()
                ->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('address', 'like', $like))
                ->orderBy('name')
                ->limit($limit)
                ->get(['id', 'name', 'code', 'address', 'status'])
                ->each(function (Pop $pop) use (&$results): void {
                    $results[] = ['type' => 'POP', 'label' => $pop->name, 'detail' => ($pop->code ?: $pop->address ?: 'POP').' · '.$pop->status, 'href' => '/operations/pops/'.$pop->id];
                });

            IpPool::query()
                ->with('router')
                ->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('cidr', 'like', $like)->orWhere('gateway', 'like', $like))
                ->orderBy('name')
                ->limit($limit)
                ->get(['id', 'name', 'cidr', 'gateway', 'router_id', 'is_active'])
                ->each(function (IpPool $pool) use (&$results): void {
                    $router = $pool->router?->name ?: $pool->gateway;
                    $results[] = ['type' => 'IP pool', 'label' => $pool->name, 'detail' => $pool->cidr.' · '.$router, 'href' => '/operations/ip-pools'];
                });
        }

        if ($user->can('suppliers.view')) {
            Supplier::query()
                ->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('contact_email', 'like', $like))
                ->orderBy('name')
                ->limit($limit)
                ->get(['id', 'name', 'code', 'contact_email', 'is_active'])
                ->each(function (Supplier $supplier) use (&$results): void {
                    $results[] = ['type' => 'supplier', 'label' => $supplier->name, 'detail' => ($supplier->code ?: $supplier->contact_email ?: 'Supplier').' · '.($supplier->is_active ? 'active' : 'inactive'), 'href' => '/operations/suppliers'];
                });
        }

        if ($user->can('wallets.view')) {
            Partner::query()
                ->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('code', 'like', $like))
                ->orderBy('name')
                ->limit($limit)
                ->get(['public_id', 'name', 'code', 'currency', 'status'])
                ->each(function (Partner $partner) use (&$results): void {
                    $results[] = ['type' => 'partner', 'label' => $partner->name, 'detail' => $partner->code.' · '.$partner->currency, 'href' => '/partners/commercial'];
                });
        }

        if ($user->can('reports.operations')) {
            CollectorTask::query()
                ->with('customer')
                ->where(fn (Builder $query) => $query
                    ->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('customer', fn (Builder $customer) => $customer
                        ->where('code', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)))
                ->latest('created_at')
                ->limit($limit)
                ->get(['public_id', 'title', 'status', 'customer_id'])
                ->each(function (CollectorTask $task) use (&$results): void {
                    $results[] = ['type' => 'collector task', 'label' => $task->title, 'detail' => $task->customer->full_name.' · '.$task->status, 'href' => '/operations/collector-tasks'];
                });
        }

        if ($user->can('users.manage') && $user->tenant_id !== null) {
            User::query()
                ->where('tenant_id', $user->tenant_id)
                ->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('email', 'like', $like))
                ->orderBy('name')
                ->limit($limit)
                ->get(['id', 'name', 'email', 'role'])
                ->each(function (User $staff) use (&$results): void {
                    $results[] = ['type' => 'staff', 'label' => $staff->name, 'detail' => $staff->email.' · '.$staff->role, 'href' => '/settings/users'];
                });
        }

        return $results;
    }

    /** @return list<array{type: string, label: string, detail: string, href: string, localized: bool}> */
    private function pageResults(User $user, string $needle): array
    {
        $results = [];

        foreach ($this->pages->availableFor($user) as $page) {
            if (! $this->pages->matchesSearch($page, $needle)) {
                continue;
            }

            $results[] = [
                'type' => 'page',
                'label' => $page['label'],
                'detail' => $page['detail'],
                'href' => $page['href'],
                'localized' => true,
            ];
        }

        return $results;
    }
}
