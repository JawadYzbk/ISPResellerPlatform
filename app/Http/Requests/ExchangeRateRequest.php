<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'base_currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'quote_currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'rate_numerator' => ['required', 'integer', 'min:1'],
            'rate_denominator' => ['required', 'integer', 'min:1'],
            'effective_from' => ['required', 'date'],
            'source' => ['required', 'string', 'max:80'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'base_currency' => strtoupper($this->string('base_currency')->toString()),
            'quote_currency' => strtoupper($this->string('quote_currency')->toString()),
        ]);
    }
}
