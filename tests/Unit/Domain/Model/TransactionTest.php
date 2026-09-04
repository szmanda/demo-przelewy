<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model;

use App\Domain\Exception\InvalidStateTransitionException;
use App\Domain\Model\Money;
use App\Domain\Model\Transaction;
use App\Domain\Model\TransactionStatus;
use PHPUnit\Framework\TestCase;

final class TransactionTest extends TestCase
{
    private function createSampleTransaction(): Transaction
    {
        return new Transaction(
            id: '550e8400-e29b-41d4-a716-446655440000',
            sessionId: 'sess_12345678',
            merchantId: 100234,
            money: new Money(25000, 'PLN'),
            description: 'Order #9872',
            email: 'customer@example.com',
            clientIp: '192.168.1.1'
        );
    }

    public function testInitialStatusIsCreated(): void
    {
        $tx = $this->createSampleTransaction();

        $this->assertSame(TransactionStatus::CREATED, $tx->getStatus());
        $this->assertNull($tx->getToken());
        $this->assertNull($tx->getPaymentMethod());
    }

    public function testRegisterTokenTransitionsToPending(): void
    {
        $tx = $this->createSampleTransaction();
        $tx->registerToken('TOKEN_ABC_123');

        $this->assertSame(TransactionStatus::PENDING, $tx->getStatus());
        $this->assertSame('TOKEN_ABC_123', $tx->getToken());
    }

    public function testAuthorizeTransitionsToAuthorized(): void
    {
        $tx = $this->createSampleTransaction();
        $tx->registerToken('TOKEN_ABC_123');
        $tx->authorize('BLIK');

        $this->assertSame(TransactionStatus::AUTHORIZED, $tx->getStatus());
        $this->assertSame('BLIK', $tx->getPaymentMethod());
    }

    public function testCaptureTransitionsToCaptured(): void
    {
        $tx = $this->createSampleTransaction();
        $tx->registerToken('TOKEN_ABC_123');
        $tx->authorize('CARD');
        $tx->capture();

        $this->assertSame(TransactionStatus::CAPTURED, $tx->getStatus());
    }

    public function testRefundCapturedTransaction(): void
    {
        $tx = $this->createSampleTransaction();
        $tx->registerToken('TOKEN_ABC_123');
        $tx->capture('PBL');
        $tx->refund();

        $this->assertSame(TransactionStatus::REFUNDED, $tx->getStatus());
    }

    public function testInvalidTransitionThrowsException(): void
    {
        $tx = $this->createSampleTransaction();

        $this->expectException(InvalidStateTransitionException::class);
        // Direct jump from CREATED to CAPTURED is disallowed
        $tx->capture();
    }

    public function testCannotRefundUncapturedTransaction(): void
    {
        $tx = $this->createSampleTransaction();
        $tx->registerToken('TOKEN_ABC_123');

        $this->expectException(InvalidStateTransitionException::class);
        $tx->refund();
    }
}
