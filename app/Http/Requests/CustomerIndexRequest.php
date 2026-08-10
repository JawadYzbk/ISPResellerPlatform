<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CustomerIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
            'zone_id' => ['nullable', 'integer', 'min:1'],
            'expires_from' => ['nullable', 'date_format:Y-m-d'],
            'expires_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:expires_from'],
            'selected' => ['nullable', 'array', 'max:1000'],
            'selected.*' => ['string', 'max:26'],
        ];
    }
}
