<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use DomainException;

final class StockQuantity
{
    public static function normalize(string $value): string
    {
        try {
            $quantity = BigDecimal::of(trim($value))->toScale(3, RoundingMode::Unnecessary);
        } catch (MathException) {
            throw new DomainException('Stock quantity must be a positive number with at most three decimal places.');
        }

        if (! $quantity->isPositive()) {
            throw new DomainException('Stock quantity must be greater than zero.');
        }

        return $quantity->__toString();
    }

    public static function add(string $left, string $right): string
    {
        return BigDecimal::of($left)->plus($right)->toScale(3, RoundingMode::Unnecessary)->__toString();
    }

    public static function subtract(string $left, string $right): string
    {
        return BigDecimal::of($left)->minus($right)->toScale(3, RoundingMode::Unnecessary)->__toString();
    }

    public static function greaterThan(string $left, string $right): bool
    {
        return BigDecimal::of($left)->compareTo($right) > 0;
    }
}
