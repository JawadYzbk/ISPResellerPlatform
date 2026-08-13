<?php

namespace App\Http\Requests;

final class EndCollectorFieldDayRequest extends CollectorLocationRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...parent::rules(), 'summary_note' => ['nullable', 'string', 'max:2000']];
    }
}
