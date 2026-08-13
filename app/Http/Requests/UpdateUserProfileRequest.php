<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\WorkspacePageCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'default_view' => [
                'nullable',
                'string',
                'max:120',
                Rule::in($this->allowedDefaultViews()),
            ],
        ];
    }

    /** @return list<string> */
    private function allowedDefaultViews(): array
    {
        $user = $this->user();

        return $user instanceof User
            ? array_column(app(WorkspacePageCatalog::class)->defaultViewsFor($user), 'href')
            : ['/dashboard'];
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
