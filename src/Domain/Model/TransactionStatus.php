<?php

declare(strict_types=1);

namespace App\Domain\Model;

enum TransactionStatus: string
{
    case CREATED = 'CREATED';
    case PENDING = 'PENDING';
    case AUTHORIZED = 'AUTHORIZED';
    case CAPTURED = 'CAPTURED';
    case REJECTED = 'REJECTED';
    case EXPIRED = 'EXPIRED';
    case REFUNDED = 'REFUNDED';

    /**
     * Check if a transition from the current status to a target status is valid.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::CREATED => in_array($target, [self::PENDING, self::REJECTED, self::EXPIRED], true),
            self::PENDING => in_array($target, [self::AUTHORIZED, self::CAPTURED, self::REJECTED, self::EXPIRED], true),
            self::AUTHORIZED => in_array($target, [self::CAPTURED, self::REJECTED, self::EXPIRED], true),
            self::CAPTURED => in_array($target, [self::REFUNDED], true),
            self::REJECTED, self::EXPIRED, self::REFUNDED => false,
        };
    }
}
