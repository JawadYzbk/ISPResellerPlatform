<?php

namespace App\Http\Requests;

use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateRouterApiRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'host' => ['required', 'string', 'max:255'],
            'api_port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['required', 'string', 'max:128'],
            'password' => ['required', 'string', 'min:12', 'max:512'],
            'radius_secret' => ['nullable', 'string', 'max:512'],
            'coa_port' => ['required', 'integer', 'between:1,65535'],
            'tls_verify' => ['boolean'],
            'pop_id' => ['nullable', 'integer', Rule::exists('pops', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['tls_verify' => $this->boolean('tls_verify')]);
    }
}
