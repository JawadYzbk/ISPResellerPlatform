<?php

use App\Support\PhoneNormalizer;

it('normalizes a Lebanese phone number for indexed search', function (): void {
    expect(app(PhoneNormalizer::class)->normalize('+961 70 123 456'))->toBe('96170123456');
});

it('rejects invalid phone numbers', function (): void {
    expect(fn (): string => app(PhoneNormalizer::class)->normalize('123'))->toThrow(InvalidArgumentException::class);
});
