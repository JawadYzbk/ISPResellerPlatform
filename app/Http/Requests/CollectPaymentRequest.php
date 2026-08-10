<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\Invoice;
use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CollectPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', Rule::in([$customer instanceof Customer ? $customer->balance_currency : ''])],
            'method' => ['required', 'string', Rule::in(['cash', 'bank_transfer', 'card', 'mobile_wallet'])],
            'invoice_id' => [
                'nullable',
                'string',
                Rule::exists('invoices', 'public_id')->where(function ($query) use ($customer): void {
                    $query->where('tenant_id', app(Tenancy::class)->requireId())
                        ->when($customer instanceof Customer, fn ($query) => $query->where('customer_id', $customer->id));
                }),
            ],
            'idempotency_key' => ['required', 'uuid', 'max:128'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $customer = $this->route('customer');
            $invoiceId = $this->string('invoice_id')->toString();
            if (! $customer instanceof Customer || $invoiceId === '') {
                return;
            }

            $invoice = Invoice::query()
                ->where('public_id', $invoiceId)
                ->where('customer_id', $customer->id)
                ->with(['payments.allocations', 'creditNotes'])
                ->first();
            if (! $invoice instanceof Invoice) {
                return;
            }

            $allocated = $invoice->payments->sum(fn ($payment): int => $payment->allocations
                ->where('invoice_id', $invoice->id)
                ->sum('amount'));
            $credited = $invoice->creditNotes->where('status', 'issued')->sum('amount');
            $outstanding = max(0, $invoice->total_amount - $allocated - $credited);

            if ((int) $this->input('amount') > $outstanding) {
                $validator->errors()->add('amount', 'The payment cannot exceed the selected invoice balance.');
            }
        }];
    }
}
