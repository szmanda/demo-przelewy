<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class NotificationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $sessionId,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $merchantId,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $orderId,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $amount,

        #[Assert\NotBlank]
        #[Assert\Length(exactly: 3)]
        public string $currency,

        #[Assert\NotBlank]
        public string $paymentMethod,

        #[Assert\NotBlank]
        public string $signature
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sessionId: (string) ($data['sessionId'] ?? ''),
            merchantId: (int) ($data['merchantId'] ?? 0),
            orderId: (int) ($data['orderId'] ?? 0),
            amount: (int) ($data['amount'] ?? 0),
            currency: (string) ($data['currency'] ?? 'PLN'),
            paymentMethod: (string) ($data['paymentMethod'] ?? 'BLIK'),
            signature: (string) ($data['signature'] ?? '')
        );
    }
}
