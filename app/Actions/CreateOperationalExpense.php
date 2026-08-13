<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CashShift;
use App\Models\ExpenseCategory;
use App\Models\ExpenseVendor;
use App\Models\OperationalExpense;
use App\Models\User;
use App\Support\Tenancy;
use DomainException;

final readonly class CreateOperationalExpense implements Action
{
    public function __construct(private GetCurrencyCatalog $currencies) {}

    /** @param array{expense_category_id: int, expense_vendor_id?: int|null, collector_id?: int|null, cash_shift_id?: int|null, payment_source: string, amount: int, currency: string, description: string, reference?: string|null, incurred_at?: string|null} $data */
    public function handle(User $actor, array $data): OperationalExpense
    {
        $tenantId = app(Tenancy::class)->requireId();
        if (! $actor->can('expenses.create') || (int) $actor->tenant_id !== $tenantId) {
            throw new DomainException('You are not allowed to submit expenses in this workspace.');
        }

        $category = ExpenseCategory::query()->whereKey($data['expense_category_id'])->where('is_active', true)->first();
        if ($category === null) {
            throw new DomainException('Choose an active expense category.');
        }
        $vendor = filled($data['expense_vendor_id'] ?? null)
            ? ExpenseVendor::query()->whereKey($data['expense_vendor_id'])->where('is_active', true)->first()
            : null;
        if (filled($data['expense_vendor_id'] ?? null) && $vendor === null) {
            throw new DomainException('Choose an active expense vendor.');
        }
        if (! in_array($data['payment_source'], OperationalExpense::PAYMENT_SOURCES, true)) {
            throw new DomainException('Choose a supported expense payment source.');
        }
        if ($data['amount'] <= 0 || trim($data['description']) === '') {
            throw new DomainException('Provide a positive amount and expense description.');
        }
        $currency = strtoupper($data['currency']);
        if (! in_array($currency, array_column($this->currencies->handle(), 'code'), true)) {
            throw new DomainException('Choose a supported expense currency.');
        }

        $collector = null;
        $shift = null;
        if ($data['payment_source'] === 'collector') {
            $collectorId = $actor->role === 'collector' ? $actor->id : ($data['collector_id'] ?? null);
            $collector = User::query()->whereKey($collectorId)->where('role', 'collector')->first();
            if ($collector === null) {
                throw new DomainException('Choose a collector for a collector-paid expense.');
            }
            if (filled($data['cash_shift_id'] ?? null)) {
                $shift = CashShift::query()->whereKey($data['cash_shift_id'])->where('user_id', $collector->id)->first();
                if ($shift === null) {
                    throw new DomainException('The selected cash shift does not belong to this collector.');
                }
            }
        }

        return OperationalExpense::create([
            'tenant_id' => $tenantId,
            'expense_category_id' => $category->id,
            'expense_vendor_id' => $vendor?->id,
            'requested_by_id' => $actor->id,
            'collector_id' => $collector?->id,
            'cash_shift_id' => $shift?->id,
            'status' => 'pending',
            'payment_source' => $data['payment_source'],
            'amount' => $data['amount'],
            'currency' => $currency,
            'description' => trim($data['description']),
            'reference' => filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
            'incurred_at' => $data['incurred_at'] ?? now(),
        ]);
    }
}
