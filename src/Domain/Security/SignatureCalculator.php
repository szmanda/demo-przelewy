<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Domain\Exception\InvalidSignatureException;

/**
 * Calculates and verifies cryptographic checksums for payment sessions and notifications
 * following Przelewy24 / Nexi security standards (SHA-384 / SHA-256 JSON/String hashing).
 */
final class SignatureCalculator
{
    public function __construct(
        private readonly string $algo = 'sha384'
    ) {
    }

    /**
     * Calculate signature from array parameters and merchant CRC key.
     *
     * @param array<string, mixed> $parameters
     */
    public function calculate(array $parameters, string $crcKey): string
    {
        $parametersWithCrc = array_merge($parameters, ['crc' => $crcKey]);
        $json = json_encode($parametersWithCrc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash($this->algo, (string) $json);
    }

    /**
     * Calculate registration signature: sessionId, merchantId, amount, currency, crc.
     */
    public function calculateForRegistration(
        string $sessionId,
        int $merchantId,
        int $amountInMinorUnits,
        string $currency,
        string $crcKey
    ): string {
        return $this->calculate([
            'sessionId' => $sessionId,
            'merchantId' => $merchantId,
            'amount' => $amountInMinorUnits,
            'currency' => $currency,
        ], $crcKey);
    }

    /**
     * Calculate notification signature: sessionId, orderId, amount, currency, crc.
     */
    public function calculateForNotification(
        string $sessionId,
        int $orderId,
        int $amountInMinorUnits,
        string $currency,
        string $crcKey
    ): string {
        return $this->calculate([
            'sessionId' => $sessionId,
            'orderId' => $orderId,
            'amount' => $amountInMinorUnits,
            'currency' => $currency,
        ], $crcKey);
    }

    /**
     * Verify provided signature against expected hash.
     *
     * @throws InvalidSignatureException
     */
    public function verify(string $expectedSignature, string $actualSignature): void
    {
        if (!hash_equals($expectedSignature, $actualSignature)) {
            throw InvalidSignatureException::mismatch($expectedSignature, $actualSignature);
        }
    }
}
