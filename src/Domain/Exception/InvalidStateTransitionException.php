<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\Model\TransactionStatus;
use DomainException;

final class InvalidStateTransitionException extends DomainException
{
    public static function create(TransactionStatus $from, TransactionStatus $to): self
    {
        return new self(sprintf('Cannot transition transaction from status "%s" to "%s".', $from->value, $to->value));
    }
}
