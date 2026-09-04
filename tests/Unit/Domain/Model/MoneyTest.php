<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model;

use App\Domain\Model\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testInstantiateWithValidValues(): void
    {
        $money = new Money(15050, 'PLN');

        $this->assertSame(15050, $money->amountInMinorUnits);
        $this->assertSame('PLN', $money->currency);
        $this->assertSame(150.50, $money->toMajorUnits());
    }

    public function testFromMajorUnits(): void
    {
        $money = Money::fromMajorUnits(129.99, 'EUR');

        $this->assertSame(12999, $money->amountInMinorUnits);
        $this->assertSame('EUR', $money->currency);
    }

    public function testThrowsExceptionOnNegativeAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount cannot be negative.');

        new Money(-100, 'PLN');
    }

    public function testThrowsExceptionOnInvalidCurrencyCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency must be a 3-letter ISO code.');

        new Money(100, 'PL');
    }

    public function testEqualityCheck(): void
    {
        $m1 = new Money(5000, 'PLN');
        $m2 = new Money(5000, 'pln');
        $m3 = new Money(5000, 'EUR');
        $m4 = new Money(6000, 'PLN');

        $this->assertTrue($m1->equals($m2));
        $this->assertFalse($m1->equals($m3));
        $this->assertFalse($m1->equals($m4));
    }
}
