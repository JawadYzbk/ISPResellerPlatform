<?php

namespace App\Http\Controllers\Web;

use App\Actions\AnonymizeCustomer;
use App\Actions\CreateCustomer;
use App\Actions\GetCustomerDetails;
use App\Actions\ListCustomers;
use App\Actions\RecordPayment;
use App\Actions\UpdateCustomer;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CollectPaymentRequest;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerController extends Controller
{
    public function create(Request $request): Response
    {
        $this->authorize('create', Customer::class);
        $user = $request->user();
        abort_unless($user?->tenant instanceof Tenant, 403);

        return Inertia::render('Customers/Create', ['zones' => Zone::query()->orderBy('name')->get(['id', 'name', 'code'])]);
    }

    public function store(CustomerRequest $request, CreateCustomer $createCustomer): RedirectResponse
    {
        $this->authorize('create', Customer::class);
        $user = $request->user();
        abort_unless($user?->tenant instanceof Tenant, 403);
        $customer = $createCustomer->handle($request, $user->tenant);

        return redirect()->route('customers.show', $customer->public_id)->with('success', 'Customer created.');
    }

    public function edit(Request $request, Customer $customer): Response
    {
        $this->authorize('update', $customer);

        return Inertia::render('Customers/Edit', [
            'customer' => $customer->only(['public_id', 'code', 'first_name', 'last_name', 'phone', 'email', 'zone_id', 'address', 'latitude', 'longitude', 'anonymized_at']),
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer, UpdateCustomer $updateCustomer): RedirectResponse
    {
        $this->authorize('update', $customer);
        $updateCustomer->handle($customer, $request);

        return redirect()->route('customers.show', $customer->public_id)->with('success', 'Customer updated.');
    }

    public function createPayment(Request $request, Customer $customer): Response
    {
        $this->authorize('view', $customer);
        abort_unless($request->user()?->can('payments.collect') === true, 403);

        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('status', InvoiceStatus::Issued)
            ->with('payments.allocations')
            ->latest('issued_at')
            ->get(['id', 'public_id', 'number', 'currency', 'total_amount', 'due_at'])
            ->map(function (Invoice $invoice): array {
                $allocated = $invoice->payments->sum(fn ($payment): int => $payment->allocations
                    ->where('invoice_id', $invoice->id)
                    ->sum('amount'));

                return [
                    'public_id' => $invoice->public_id,
                    'number' => $invoice->number,
                    'currency' => $invoice->currency,
                    'total_amount' => $invoice->total_amount,
                    'outstanding_amount' => max(0, $invoice->total_amount - $allocated),
                    'due_at' => $invoice->due_at?->toIso8601String(),
                ];
            })
            ->filter(fn (array $invoice): bool => $invoice['outstanding_amount'] > 0)
            ->values();

        return Inertia::render('Payments/Create', [
            'customer' => $customer->only(['public_id', 'code', 'first_name', 'last_name', 'balance_amount', 'balance_currency']),
            'invoices' => $invoices,
        ]);
    }

    public function storePayment(CollectPaymentRequest $request, Customer $customer, RecordPayment $recordPayment): RedirectResponse
    {
        $this->authorize('view', $customer);
        abort_unless($request->user()?->can('payments.collect') === true, 403);
        $invoice = filled($request->validated('invoice_id'))
            ? Invoice::query()->where('public_id', $request->validated('invoice_id'))->where('customer_id', $customer->id)->firstOrFail()
            : null;
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $payment = $recordPayment->handle(
            $customer,
            (int) $request->validated('amount'),
            strtoupper((string) $request->validated('currency')),
            (string) $request->validated('method'),
            (string) $request->validated('idempotency_key'),
            $invoice,
            $user,
        );

        return redirect()->route('customers.show', $customer->public_id)->with('success', "Payment {$payment->number} recorded.");
    }

    public function index(Request $request, ListCustomers $listCustomers): Response
    {
        $this->authorize('viewAny', Customer::class);

        return Inertia::render('Customers/Index', [
            'customers' => $listCustomers->handle($request->string('search')->toString() ?: null, $request->string('status')->toString() ?: null),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show(Request $request, Customer $customer, GetCustomerDetails $getCustomerDetails): Response
    {
        $this->authorize('view', $customer);

        return Inertia::render('Customers/Show', [
            'customer' => $getCustomerDetails->handle($customer),
            'canAnonymize' => $request->user()?->can('customers.anonymize') === true,
            'canCreateService' => $request->user()?->can('services.create') === true,
            'canEdit' => $request->user()?->can('customers.update') === true,
            'canCollectPayment' => $request->user()?->can('payments.collect') === true,
            'canResyncServices' => $request->user()?->can('services.activate') === true || $request->user()?->can('services.suspend') === true,
        ]);
    }

    public function anonymize(Request $request, Customer $customer, AnonymizeCustomer $anonymize): RedirectResponse
    {
        $this->authorize('anonymize', $customer);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $anonymize->handle($customer, $user);

        return redirect()->route('customers.show', $customer->public_id)->with('success', 'Customer data anonymized.');
    }
}
