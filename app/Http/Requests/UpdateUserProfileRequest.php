<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['nullable', 'in:en,ar,fr'],
            'timezone' => ['nullable', 'timezone'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $timezone = trim((string) $this->input('timezone', ''));

        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'locale' => ($locale = strtolower(trim((string) $this->input('locale', '')))) === '' ? null : $locale,
            'timezone' => $timezone === '' ? null : $timezone,
        ]);
    }
}
