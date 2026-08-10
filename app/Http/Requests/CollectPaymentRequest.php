<?php

namespace App\Http\Requests;

use App\Models\Currency;
use App\Models\Customer;
use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'currency' => ['required', 'string', 'size:3', Rule::in(Currency::query()->where('is_active', true)->pluck('code')->all())],
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
            'fx_override' => ['sometimes', 'boolean'],
            'fx_rate_numerator' => ['nullable', 'integer', 'min:1', Rule::requiredIf(fn (): bool => $this->boolean('fx_override'))],
            'fx_rate_denominator' => ['nullable', 'integer', 'min:1', Rule::requiredIf(fn (): bool => $this->boolean('fx_override'))],
            'fx_override_reason' => ['nullable', 'string', 'max:500', Rule::requiredIf(fn (): bool => $this->boolean('fx_override'))],
            'reference' => ['nullable', 'string', 'max:128'],
        ];
    }
}
