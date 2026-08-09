<?php

namespace App\Support;

use Brick\Money\AllocationMode;
use Brick\Money\Money as BrickMoney;
use InvalidArgumentException;

final readonly class Money
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Currency must be an ISO-4217 code.');
        }
    }

    public static function zero(string $currency): self
    {
        return new self(0, strtoupper($currency));
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->brick()->plus($other->brick())->getMinorAmount()->toInt(), $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->brick()->minus($other->brick())->getMinorAmount()->toInt(), $this->currency);
    }

    /** @return list<self> */
    public function allocate(int ...$ratios): array
    {
        if ($ratios === [] || array_sum($ratios) <= 0 || in_array(0, $ratios, true)) {
            throw new InvalidArgumentException('Allocation ratios must be positive integers.');
        }

        return array_map(
            fn (BrickMoney $part): self => new self($part->getMinorAmount()->toInt(), $this->currency),
            $this->brick()->allocate($ratios, AllocationMode::FloorToLargestRemainder),
        );
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amount === $other->amount;
    }

    public function jsonSerialize(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency];
    }

    private function brick(): BrickMoney
    {
        return BrickMoney::ofMinor($this->amount, $this->currency);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Money values must use the same currency.');
        }
    }
}
