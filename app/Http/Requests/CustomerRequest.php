<?php

namespace App\Http\Requests;

use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'zone_id' => ['nullable', Rule::exists('zones', 'id')->where(fn ($query) => $query->where('tenant_id', app(Tenancy::class)->requireId()))],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
