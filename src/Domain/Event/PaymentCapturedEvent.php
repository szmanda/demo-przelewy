<?php

declare(strict_types=1);

namespace App\Domain\Event;

use DateTimeImmutable;

final readonly class PaymentCapturedEvent
{
    public function __construct(
        public string $transactionId,
        public string $sessionId,
        public int $merchantId,
        public int $amountInMinorUnits,
        public string $currency,
        public string $email,
        public string $clientIp,
        public string $paymentMethod,
        public DateTimeImmutable $capturedAt
    ) {
    }
}
