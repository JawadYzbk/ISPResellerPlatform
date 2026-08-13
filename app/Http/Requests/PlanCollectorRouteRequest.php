<?php

namespace App\Http\Requests;

use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PlanCollectorRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reports.operations');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = app(Tenancy::class)->requireId();

        return [
            'collector_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('role', 'collector')),
            ],
            'route_date' => ['required', 'date_format:Y-m-d'],
            'customer_ids' => ['required', 'array', 'min:1', 'max:250'],
            'customer_ids.*' => [
                'required', 'integer', 'distinct',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
        ];
    }
}
