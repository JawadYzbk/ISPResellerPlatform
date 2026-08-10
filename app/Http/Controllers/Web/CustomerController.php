<?php

namespace App\Http\Controllers\Web;

use App\Actions\AnonymizeCustomer;
use App\Actions\CreateCustomer;
use App\Actions\CreateRenewalInvoice;
use App\Actions\DeleteCustomerView;
use App\Actions\ExportCustomersCsv;
use App\Actions\GetCustomerDetails;
use App\Actions\ListCustomers;
use App\Actions\ListCustomerSavedViews;
use App\Actions\RecordPayment;
use App\Actions\SaveCustomerView;
use App\Actions\StoreMediaUpload;
use App\Actions\UpdateCustomer;
use App\Domain\Money\FxConverter;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CollectPaymentRequest;
use App\Http\Requests\CustomerIndexRequest;
use App\Http\Requests\CustomerRequest;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerSavedView;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Router;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Zone;
use App\Support\Tenancy;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CustomerController extends Controller
{
    public function create(Request $request): Response
    {
        $this->authorize('create', Customer::class);
        $user = $request->user();
        abort_unless($user?->tenant instanceof Tenant, 403);

        $canCreateService = $user->can('services.create');

        return Inertia::render('Customers/Create', [
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name', 'code']),
            'canCreateService' => $canCreateService,
            'plans' => $canCreateService ? Plan::query()->where('status', 'active')->orderBy('name')->get(['id', 'public_id', 'name', 'download_kbps', 'upload_kbps', 'duration_days', 'amount_minor', 'currency']) : [],
            'routers' => $canCreateService ? Router::query()->orderBy('name')->get(['id', 'public_id', 'name']) : [],
        ]);
    }

    public function store(CustomerRequest $request, CreateCustomer $createCustomer): RedirectResponse
    {
        $this->authorize('create', Customer::class);
        $user = $request->user();
        abort_unless($user?->tenant instanceof Tenant, 403);
        abort_unless(! $request->boolean('create_service') || $user->can('services.create'), 403);
        $customer = $createCustomer->handle($request, $user->tenant, $user);

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

    public function storeDocument(Request $request, Customer $customer, StoreMediaUpload $store): RedirectResponse
    {
        $this->authorize('update', $customer);
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('customers.update'), 403);
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp'],
            'document_type' => ['required', 'string', Rule::in(['contract', 'identity', 'proof_of_address', 'other'])],
            'retention_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422, 'A document file is required.');
        $store->handle($file, $user, null, 'document', $customer, (string) $request->string('document_type'), $request->string('retention_until')->toString() ?: null);

        return redirect()->route('customers.show', $customer->public_id)->with('success', 'Customer document uploaded.');
    }

    public function createPayment(Request $request, Customer $customer, FxConverter $fx): Response
    {
        $this->authorize('view', $customer);
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('payments.collect'), 403);

        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('status', InvoiceStatus::Issued)
            ->with(['payments.allocations', 'creditNotes'])
            ->latest('issued_at')
            ->get(['id', 'public_id', 'number', 'currency', 'total_amount', 'due_at'])
            ->map(function (Invoice $invoice): array {
                $allocated = $invoice->payments
                    ->where('status', PaymentStatus::Posted)
                    ->sum(fn ($payment): int => $payment->allocations
                        ->where('invoice_id', $invoice->id)
                        ->sum('amount'));
                $credited = $invoice->creditNotes->sum('amount');

                return [
                    'public_id' => $invoice->public_id,
                    'number' => $invoice->number,
                    'currency' => $invoice->currency,
                    'total_amount' => $invoice->total_amount,
                    'outstanding_amount' => max(0, $invoice->total_amount - $allocated - $credited),
                    'due_at' => $invoice->due_at?->toIso8601String(),
                ];
            })
            ->filter(fn (array $invoice): bool => $invoice['outstanding_amount'] > 0)
            ->values();

        $paymentCurrencies = Currency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name', 'decimal_digits'])
            ->map(function (Currency $currency) use ($customer, $fx): array {
                $rate = null;
                try {
                    $rate = $fx->snapshot($currency->code, $customer->balance_currency, now()->toImmutable());
                } catch (DomainException) {
                    // The collector can still choose this currency and enter an approved override.
                }

                return [
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'decimal_digits' => $currency->decimal_digits,
                    'rate' => $rate?->toArray(),
                ];
            })
            ->values();

        return Inertia::render('Payments/Create', [
            'customer' => $customer->only(['public_id', 'code', 'first_name', 'last_name', 'balance_amount', 'balance_currency']),
            'invoices' => $invoices,
            'defaultCurrency' => (string) (Tenant::query()->whereKey($user->tenant_id)->value('collection_currency') ?? $customer->balance_currency),
            'paymentCurrencies' => $paymentCurrencies,
        ]);
    }

    public function renew(Request $request, Customer $customer): Response
    {
        $this->authorize('view', $customer);
        abort_unless($request->user()?->can('payments.collect') === true, 403);
        $services = $customer->services()
            ->with(['plan.prices'])
            ->where('status', '!=', 'terminated')
            ->orderBy('username')
            ->get()
            ->map(function (Service $service): array {
                $price = $service->plan?->priceAt();

                return [
                    'public_id' => $service->public_id,
                    'username' => $service->username,
                    'status' => $service->status->value,
                    'expires_at' => $service->expires_at?->toIso8601String(),
                    'plan' => $service->plan?->only(['name', 'duration_days']),
                    'price' => $price?->only(['amount_minor', 'currency']),
                ];
            })
            ->values();

        return Inertia::render('Customers/Renew', [
            'customer' => $customer->only(['public_id', 'code', 'first_name', 'last_name', 'balance_currency']),
            'services' => $services,
        ]);
    }

    public function storeRenewal(Request $request, Customer $customer, CreateRenewalInvoice $createRenewalInvoice): RedirectResponse
    {
        $this->authorize('view', $customer);
        abort_unless($request->user()?->can('payments.collect') === true, 403);
        $validated = $request->validate([
            'service_id' => [
                'required',
                'string',
                Rule::exists('services', 'public_id')->where(fn ($query) => $query->where('tenant_id', app(Tenancy::class)->requireId())->where('customer_id', $customer->id)),
            ],
        ]);
        $service = Service::query()->with('plan')->where('public_id', $validated['service_id'])->where('customer_id', $customer->id)->firstOrFail();

        try {
            $invoice = $createRenewalInvoice->handle($customer, $service, $request->user());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['service_id' => $exception->getMessage()]);
        }

        return redirect()->route('customers.payments.create', $customer->public_id)->with('success', "Renewal invoice {$invoice->number} is ready for collection.");
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

        $validated = $request->validated();
        try {
            $payment = $recordPayment->handle(
                $customer,
                (int) $validated['amount'],
                strtoupper((string) $validated['currency']),
                (string) $validated['method'],
                (string) $validated['idempotency_key'],
                $invoice,
                $user,
                null,
                ($validated['fx_override'] ?? false) ? (int) $validated['fx_rate_numerator'] : null,
                ($validated['fx_override'] ?? false) ? (int) $validated['fx_rate_denominator'] : null,
                ($validated['fx_override'] ?? false) ? (string) $validated['fx_override_reason'] : null,
                isset($validated['reference']) ? (string) $validated['reference'] : null,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['currency' => $exception->getMessage()]);
        }

        return redirect()->route('customers.show', $customer->public_id)->with('success', "Payment {$payment->number} recorded.");
    }

    public function index(CustomerIndexRequest $request, ListCustomers $listCustomers): Response
    {
        $this->authorize('viewAny', Customer::class);
        $validated = $request->validated();

        return Inertia::render('Customers/Index', [
            'customers' => $listCustomers->handle(
                $validated['search'] ?? null,
                $validated['status'] ?? null,
                isset($validated['zone_id']) ? (int) $validated['zone_id'] : null,
                $validated['expires_from'] ?? null,
                $validated['expires_to'] ?? null,
            ),
            'filters' => $request->only(['search', 'status', 'zone_id', 'expires_from', 'expires_to']),
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name', 'code']),
            'savedViews' => $request->user() instanceof User
                ? app(ListCustomerSavedViews::class)->handle($request->user())->map(fn (CustomerSavedView $view): array => [
                    'id' => $view->id,
                    'name' => $view->name,
                    'filters' => $view->filters,
                    'columns' => $view->columns,
                ])->values()
                : [],
            'canExport' => $request->user()?->can('customers.export') === true,
        ]);
    }

    public function export(CustomerIndexRequest $request, ExportCustomersCsv $export): StreamedResponse
    {
        $this->authorize('export', Customer::class);
        $user = $request->user();
        abort_unless($user instanceof User && $user->tenant instanceof Tenant, 403);
        $tenant = $user->tenant;
        $validated = $request->validated();
        $query = array_filter([
            'search' => $validated['search'] ?? null,
            'status' => $validated['status'] ?? null,
            'zone_id' => isset($validated['zone_id']) ? (int) $validated['zone_id'] : null,
            'expires_from' => $validated['expires_from'] ?? null,
            'expires_to' => $validated['expires_to'] ?? null,
            'selected' => $validated['selected'] ?? [],
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        return response()->streamDownload(function () use ($export, $query, $tenant): void {
            app(Tenancy::class)->run($tenant, function () use ($export, $query): void {
                echo $export->handle(
                    $query['search'] ?? null,
                    $query['status'] ?? null,
                    $query['zone_id'] ?? null,
                    $query['expires_from'] ?? null,
                    $query['expires_to'] ?? null,
                    $query['selected'] ?? [],
                );
            });
        }, 'customers.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function storeSavedView(Request $request, SaveCustomerView $save): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('customers.view'), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'filters' => ['nullable', 'array'],
            'columns' => ['nullable', 'array', 'max:5'],
            'columns.*' => ['string', 'max:32'],
        ]);
        $save->handle($user, $validated);

        return redirect()->route('customers.index')->with('success', 'Customer view saved.');
    }

    public function destroySavedView(Request $request, CustomerSavedView $savedView, DeleteCustomerView $delete): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('customers.view'), 403);
        $delete->handle($user, $savedView);

        return redirect()->route('customers.index')->with('success', 'Customer view deleted.');
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
            'canCreateTicket' => $request->user()?->can('tickets.create') === true,
            'canResyncServices' => $request->user()?->can('services.activate') === true || $request->user()?->can('services.suspend') === true || $request->user()?->can('services.pause') === true,
            'canActivateServices' => $request->user()?->can('services.activate') === true,
            'canSuspendServices' => $request->user()?->can('services.suspend') === true,
            'canPauseServices' => $request->user()?->can('services.pause') === true,
            'canTerminateServices' => $request->user()?->can('services.terminate') === true,
            'canDisconnectSessions' => $request->user()?->can('network.disconnect') === true,
            'canForceResumeServices' => $request->user()?->can('services.force_resume') === true,
            'canManageEquipment' => $request->user()?->can('inventory.assign') === true,
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
