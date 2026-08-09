<?php

namespace App\Support;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

final class DocumentNumberGenerator
{
    public function next(string $key, string $prefix): string
    {
        return DB::transaction(function () use ($key, $prefix): string {
            $sequence = DocumentSequence::query()->where('key', $key)->lockForUpdate()->firstOrFail();
            $value = $sequence->next_value;
            $sequence->increment('next_value');

            return $prefix.'-'.str_pad((string) $value, 5, '0', STR_PAD_LEFT);
        });
    }
}
