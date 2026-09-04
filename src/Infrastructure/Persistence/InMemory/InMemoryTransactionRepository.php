<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\InMemory;

use App\Domain\Model\Transaction;
use App\Domain\Repository\TransactionRepositoryInterface;

final class InMemoryTransactionRepository implements TransactionRepositoryInterface
{
    /** @var array<string, Transaction> */
    private static array $storage = [];

    public function save(Transaction $transaction): void
    {
        self::$storage[$transaction->getId()] = $transaction;
    }

    public function findById(string $id): ?Transaction
    {
        return self::$storage[$id] ?? null;
    }

    public function findBySessionIdAndMerchantId(string $sessionId, int $merchantId): ?Transaction
    {
        foreach (self::$storage as $tx) {
            if ($tx->getSessionId() === $sessionId && $tx->getMerchantId() === $merchantId) {
                return $tx;
            }
        }

        return null;
    }

    public static function clear(): void
    {
        self::$storage = [];
    }
}
