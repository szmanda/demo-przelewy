<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Transaction;

interface TransactionRepositoryInterface
{
    public function save(Transaction $transaction): void;

    public function findById(string $id): ?Transaction;

    public function findBySessionIdAndMerchantId(string $sessionId, int $merchantId): ?Transaction;
}
