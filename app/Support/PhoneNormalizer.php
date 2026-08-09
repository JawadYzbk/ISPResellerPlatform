<?php

namespace App\Support;

use InvalidArgumentException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

final class PhoneNormalizer
{
    public function normalize(string $phone, string $region = 'LB'): string
    {
        try {
            $util = PhoneNumberUtil::getInstance();
            $parsed = $util->parse($phone, $region);
        } catch (NumberParseException $exception) {
            throw new InvalidArgumentException('The phone number could not be parsed.', previous: $exception);
        }

        if (! $util->isValidNumber($parsed)) {
            throw new InvalidArgumentException('The phone number is not valid.');
        }

        return preg_replace('/\D+/', '', $util->format($parsed, PhoneNumberFormat::E164));
    }
}
