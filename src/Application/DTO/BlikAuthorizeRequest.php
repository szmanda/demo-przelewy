<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class BlikAuthorizeRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $sessionId,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $merchantId,

        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{6}$/', message: 'BLIK code must be exactly 6 digits.')]
        public string $blikCode
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
            blikCode: (string) ($data['blikCode'] ?? '')
        );
    }
}
