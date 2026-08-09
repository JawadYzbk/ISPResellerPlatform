<?php

namespace App\Support;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<Money|null, Money|array<string, int|string>|null> */
final class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        $decoded = is_array($value) ? $value : json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

        return new Money((int) $decoded['amount'], strtoupper((string) $decoded['currency']));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $money = $value instanceof Money ? $value : new Money((int) $value['amount'], strtoupper((string) $value['currency']));

        return json_encode($money->jsonSerialize(), JSON_THROW_ON_ERROR);
    }
}
