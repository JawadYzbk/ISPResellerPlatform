<?php

namespace App\Support;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

final class CustomerCodeGenerator
{
    public function next(): string
    {
        return DB::transaction(function (): string {
            $sequence = DocumentSequence::query()->where('key', 'customer')->lockForUpdate()->firstOrFail();
            $value = $sequence->next_value;
            $sequence->increment('next_value');

            return 'CUS-'.str_pad((string) $value, 5, '0', STR_PAD_LEFT);
        });
    }
}
