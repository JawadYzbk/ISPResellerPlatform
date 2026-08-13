<?php

namespace App\Http\Requests;

use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCollectorTerritoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = app(Tenancy::class)->requireId();

        return [
            'all_zones' => ['required', 'boolean'],
            'zone_ids' => ['required_if:all_zones,false', 'array', 'max:100'],
            'zone_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('zones', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ];
    }
}
