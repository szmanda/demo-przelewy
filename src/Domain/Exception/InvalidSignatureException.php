<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use DomainException;

final class InvalidSignatureException extends DomainException
{
    public static function mismatch(string $expected, string $actual): self
    {
        return new self(sprintf('Cryptographic signature mismatch. Expected "%s", received "%s".', $expected, $actual));
    }
}
