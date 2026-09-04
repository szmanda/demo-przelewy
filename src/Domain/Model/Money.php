<?php

declare(strict_types=1);

namespace App\Domain\Model;

use InvalidArgumentException;

/**
 * Money Value Object storing amounts in minor currency units (e.g., grosze, cents).
 * Avoids floating point arithmetic inaccuracy in financial transactions.
 */
final readonly class Money
{
    public function __construct(
        public int $amountInMinorUnits,
        public string $currency = 'PLN'
    ) {
        if ($this->amountInMinorUnits < 0) {
            throw new InvalidArgumentException('Amount cannot be negative.');
        }

        if (strlen($this->currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a 3-letter ISO code.');
        }
    }

    public static function fromMajorUnits(float $amount, string $currency = 'PLN'): self
    {
        return new self((int) round($amount * 100), strtoupper($currency));
    }

    public function toMajorUnits(): float
    {
        return $this->amountInMinorUnits / 100.0;
    }

    public function equals(self $other): bool
    {
        return $this->amountInMinorUnits === $other->amountInMinorUnits
            && strtoupper($this->currency) === strtoupper($other->currency);
    }
}
