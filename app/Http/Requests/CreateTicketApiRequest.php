<?php

namespace App\Http\Requests;

use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateTicketApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $tenantId = app(Tenancy::class)->requireId();

        return [
            'customer_id' => ['required', 'string', Rule::exists('customers', 'public_id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'service_id' => ['nullable', 'string', Rule::exists('services', 'public_id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'subject' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:10000'],
            'category' => ['required', 'string', 'max:64'],
            'priority' => ['required', Rule::in(['critical', 'high', 'normal', 'low'])],
        ];
    }
}
