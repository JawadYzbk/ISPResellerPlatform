<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreateSupplier;
use App\Actions\CreateSupplierBill;
use App\Actions\CreateSupplierContract;
use App\Actions\GetCurrencyCatalog;
use App\Actions\RecordSupplierPayment;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierContract;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class SupplierOperationsController extends Controller
{
    public function index(Request $request, GetCurrencyCatalog $currencyCatalog): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('suppliers.view'), 403);
        $suppliers = Supplier::query()->with(['contracts', 'bills.payments'])->orderBy('name')->get();

        return Inertia::render('Operations/Suppliers', [
            'suppliers' => $suppliers->map(fn (Supplier $supplier): array => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'code' => $supplier->code,
                'contact_email' => $supplier->contact_email,
                'contracts' => $supplier->contracts->map(fn (SupplierContract $contract): array => [
                    'id' => $contract->id,
                    'service_type' => $contract->service_type,
                    'wholesale_currency' => $contract->wholesale_currency,
                    'effective_from' => $contract->effective_from->toDateString(),
                    'effective_to' => $contract->effective_to?->toDateString(),
                    'status' => $contract->status,
                ])->values()->all(),
                'bills' => $supplier->bills->map(fn (SupplierBill $bill): array => [
                    'id' => $bill->id,
                    'reference' => $bill->reference,
                    'period_start' => $bill->period_start->toDateString(),
                    'period_end' => $bill->period_end->toDateString(),
                    'amount' => $bill->amount,
                    'currency' => $bill->currency,
                    'paid_amount' => (int) $bill->payments->sum('amount'),
                    'status' => $bill->status,
                ])->values()->all(),
            ])->values()->all(),
            'canManage' => $user->can('suppliers.manage'),
            'currencies' => $user->can('suppliers.manage') ? $currencyCatalog->handle() : [],
        ]);
    }

    public function store(Request $request, CreateSupplier $create): RedirectResponse
    {
        $this->ensureManager($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'alpha_dash', Rule::unique('suppliers', 'code')->where('tenant_id', $request->user()->tenant_id)],
            'contact_email' => ['nullable', 'email', 'max:255'],
        ]);
        $create->handle($validated);

        return redirect()->route('operations.suppliers')->with('success', 'Supplier created.');
    }

    public function storeContract(Request $request, Supplier $supplier, CreateSupplierContract $create): RedirectResponse
    {
        $this->ensureManager($request);
        $validated = $request->validate($this->contractRules($request->user()->tenant_id));
        $create->handle($supplier, $validated);

        return redirect()->route('operations.suppliers')->with('success', 'Supplier contract recorded.');
    }

    public function storeBill(Request $request, Supplier $supplier, CreateSupplierBill $create): RedirectResponse
    {
        $this->ensureManager($request);
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')->where('tenant_id', $request->user()->tenant_id)->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $create->handle($supplier, $validated);

        return redirect()->route('operations.suppliers')->with('success', 'Supplier bill recorded.');
    }

    public function storePayment(Request $request, SupplierBill $bill, RecordSupplierPayment $record): RedirectResponse
    {
        $this->ensureManager($request);
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', 'string', 'max:32'],
            'reference' => ['nullable', 'string', 'max:128'],
        ]);
        $record->handle($bill, $request->user(), $validated);

        return redirect()->route('operations.suppliers')->with('success', 'Supplier payment recorded.');
    }

    /** @return array<string, array<int, mixed>> */
    private function contractRules(int $tenantId): array
    {
        return [
            'service_type' => ['required', 'string', 'max:64'],
            'wholesale_currency' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')->where('tenant_id', $tenantId)->where('is_active', true)],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['required', Rule::in(['active', 'suspended', 'expired'])],
        ];
    }

    private function ensureManager(Request $request): void
    {
        abort_unless($request->user() instanceof User && $request->user()->can('suppliers.manage'), 403);
    }
}
