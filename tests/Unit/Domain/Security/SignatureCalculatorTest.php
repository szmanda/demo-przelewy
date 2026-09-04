<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Security;

use App\Domain\Exception\InvalidSignatureException;
use App\Domain\Security\SignatureCalculator;
use PHPUnit\Framework\TestCase;

final class SignatureCalculatorTest extends TestCase
{
    private SignatureCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new SignatureCalculator('sha384');
    }

    public function testCalculateForRegistration(): void
    {
        $sessionId = 'test_session_1';
        $merchantId = 10001;
        $amount = 5000;
        $currency = 'PLN';
        $crc = 'sample_crc_key';

        $expectedJson = json_encode([
            'sessionId' => $sessionId,
            'merchantId' => $merchantId,
            'amount' => $amount,
            'currency' => $currency,
            'crc' => $crc,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $expectedHash = hash('sha384', (string) $expectedJson);

        $actualHash = $this->calculator->calculateForRegistration(
            $sessionId,
            $merchantId,
            $amount,
            $currency,
            $crc
        );

        $this->assertSame($expectedHash, $actualHash);
    }

    public function testVerifySuccess(): void
    {
        $hash = hash('sha384', 'test-data');

        // Should not throw any exception
        $this->calculator->verify($hash, $hash);
        $this->assertTrue(true);
    }

    public function testVerifyThrowsOnMismatch(): void
    {
        $this->expectException(InvalidSignatureException::class);

        $this->calculator->verify('valid_hash_123', 'tampered_hash_456');
    }
}
