<?php

namespace App\Http\Controllers\Web;

use App\Actions\RecordFieldInventorySale;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class FieldInventorySaleController extends Controller
{
    public function store(Request $request, RecordFieldInventorySale $record): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->role === 'collector' && $user->can('payments.collect'), 403);
        $validated = $request->validate([
            'customer_id' => ['required', 'string'],
            'warehouse_id' => ['required', 'integer'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_method' => ['required', 'string', 'in:cash,card,bank_transfer,mobile_wallet'],
            'idempotency_key' => ['required', 'string', 'max:120'],
            'lines' => ['required', 'array', 'min:1', 'max:25'],
            'lines.*.inventory_item_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity' => ['required', 'string', 'regex:/^\d{1,9}(?:\.\d{1,3})?$/'],
            'lines.*.unit_amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $sale = $record->handle(
                $user,
                Customer::query()->where('public_id', $validated['customer_id'])->firstOrFail(),
                Warehouse::query()->findOrFail($validated['warehouse_id']),
                $validated['lines'],
                (string) $validated['currency'],
                (string) $validated['payment_method'],
                (string) $validated['idempotency_key'],
                $validated['note'] ?? null,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['lines' => $exception->getMessage()]);
        }

        return redirect()->route('field.index')->with('success', 'Sale '.$sale->public_id.' recorded.');
    }
}
