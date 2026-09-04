<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use App\Domain\Model\Money;
use App\Domain\Model\Transaction;
use App\Domain\Model\TransactionStatus;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'transactions')]
#[ORM\Index(name: 'idx_session_merchant', columns: ['session_id', 'merchant_id'])]
#[ORM\Index(name: 'idx_status', columns: ['status'])]
#[ORM\Index(name: 'idx_created_at', columns: ['created_at'])]
class TransactionEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 128)]
    private string $sessionId;

    #[ORM\Column(type: Types::INTEGER)]
    private int $merchantId;

    #[ORM\Column(type: Types::INTEGER)]
    private int $amount;

    #[ORM\Column(type: Types::STRING, length: 3)]
    private string $currency;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 45)]
    private string $clientIp;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $status;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    private ?string $token = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public static function fromDomain(Transaction $domain): self
    {
        $entity = new self();
        $entity->id = $domain->getId();
        $entity->sessionId = $domain->getSessionId();
        $entity->merchantId = $domain->getMerchantId();
        $entity->amount = $domain->getMoney()->amountInMinorUnits;
        $entity->currency = $domain->getMoney()->currency;
        $entity->description = $domain->getDescription();
        $entity->email = $domain->getEmail();
        $entity->clientIp = $domain->getClientIp();
        $entity->status = $domain->getStatus()->value;
        $entity->paymentMethod = $domain->getPaymentMethod();
        $entity->token = $domain->getToken();
        $entity->rejectionReason = $domain->getRejectionReason();
        $entity->createdAt = $domain->getCreatedAt();
        $entity->updatedAt = $domain->getUpdatedAt();

        return $entity;
    }

    public function toDomain(): Transaction
    {
        $transaction = new Transaction(
            id: $this->id,
            sessionId: $this->sessionId,
            merchantId: $this->merchantId,
            money: new Money($this->amount, $this->currency),
            description: $this->description,
            email: $this->email,
            clientIp: $this->clientIp,
            initialStatus: TransactionStatus::from($this->status)
        );

        if ($this->token !== null && $this->status !== TransactionStatus::CREATED->value) {
            $reflection = new \ReflectionClass($transaction);
            $tokenProp = $reflection->getProperty('token');
            $tokenProp->setAccessible(true);
            $tokenProp->setValue($transaction, $this->token);

            $methodProp = $reflection->getProperty('paymentMethod');
            $methodProp->setAccessible(true);
            $methodProp->setValue($transaction, $this->paymentMethod);
        }

        return $transaction;
    }

    public function updateFromDomain(Transaction $domain): void
    {
        $this->status = $domain->getStatus()->value;
        $this->paymentMethod = $domain->getPaymentMethod();
        $this->token = $domain->getToken();
        $this->rejectionReason = $domain->getRejectionReason();
        $this->updatedAt = $domain->getUpdatedAt();
    }

    public function getId(): string
    {
        return $this->id;
    }
}
