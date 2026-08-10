<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\ExchangeRate;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

final readonly class CreateExchangeRate implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): ExchangeRate
    {
        if ($data['base_currency'] === $data['quote_currency']) {
            throw ValidationException::withMessages(['quote_currency' => 'The quote currency must differ from the base currency.']);
        }

        try {
            return ExchangeRate::create([
                'base_currency' => $data['base_currency'],
                'quote_currency' => $data['quote_currency'],
                'rate_numerator' => $data['rate_numerator'],
                'rate_denominator' => $data['rate_denominator'],
                'effective_from' => $data['effective_from'],
                'source' => $data['source'],
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['effective_from' => 'A rate for this currency pair already exists at that effective time.']);
        }
    }
}
