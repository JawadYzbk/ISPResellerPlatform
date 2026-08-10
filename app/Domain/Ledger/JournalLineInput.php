<?php

namespace App\Domain\Ledger;

use InvalidArgumentException;

final readonly class JournalLineInput
{
    public function __construct(
        public int $accountId,
        public string $currency,
        public int $debitAmount = 0,
        public int $creditAmount = 0,
        public ?int $customerId = null,
        public ?int $partnerId = null,
        public ?string $memo = null,
    ) {
        if (($debitAmount > 0) === ($creditAmount > 0)) {
            throw new InvalidArgumentException('A journal line must contain exactly one positive debit or credit amount.');
        }
    }
}
