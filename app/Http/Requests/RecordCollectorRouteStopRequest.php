<?php

namespace App\Http\Requests;

use App\Models\CollectorRouteStop;
use Illuminate\Validation\Rule;

final class RecordCollectorRouteStopRequest extends CollectorLocationRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'outcome' => ['required', 'string', Rule::in(array_diff(CollectorRouteStop::OUTCOMES, ['pending']))],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
