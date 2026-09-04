<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Model\Transaction;
use App\Domain\Repository\TransactionRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\Entity\TransactionEntity;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function save(Transaction $transaction): void
    {
        $existing = $this->entityManager->find(TransactionEntity::class, $transaction->getId());

        if ($existing instanceof TransactionEntity) {
            $existing->updateFromDomain($transaction);
        } else {
            $entity = TransactionEntity::fromDomain($transaction);
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }

    public function findById(string $id): ?Transaction
    {
        /** @var TransactionEntity|null $entity */
        $entity = $this->entityManager->find(TransactionEntity::class, $id);

        return $entity?->toDomain();
    }

    public function findBySessionIdAndMerchantId(string $sessionId, int $merchantId): ?Transaction
    {
        $repository = $this->entityManager->getRepository(TransactionEntity::class);
        /** @var TransactionEntity|null $entity */
        $entity = $repository->findOneBy([
            'sessionId' => $sessionId,
            'merchantId' => $merchantId,
        ]);

        return $entity?->toDomain();
    }
}
