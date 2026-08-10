<?php

namespace App\Support;

use Brick\Money\Money as BrickMoney;
use Carbon\CarbonInterface;

final class BillingPdfFormatter
{
    public static function money(int $amount, string $currency): string
    {
        return BrickMoney::ofMinor($amount, $currency)->formatToLocale('en_US');
    }

    public static function date(?CarbonInterface $date, string $timezone): string
    {
        return $date?->setTimezone($timezone)->format('Y-m-d H:i') ?? '—';
    }
}
