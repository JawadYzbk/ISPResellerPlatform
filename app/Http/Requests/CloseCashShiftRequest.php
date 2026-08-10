<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CloseCashShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'declared_totals' => ['required', 'array', 'min:1'],
            'declared_totals.*' => ['required', 'integer', 'min:0'],
            'variance_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
