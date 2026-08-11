<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateWhishPaymentRequest extends FormRequest
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
            'currency' => ['required', 'string', 'size:3', Rule::in(['USD', 'LBP', 'AED'])],
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
}
