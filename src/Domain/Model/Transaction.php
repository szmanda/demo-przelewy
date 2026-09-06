<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Exception\InvalidStateTransitionException;
use DateTimeImmutable;

class Transaction
{
    private TransactionStatus $status;
    private ?string $token = null;
    private ?string $paymentMethod = null;
    private ?string $rejectionReason = null;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly string $id,
        private readonly string $sessionId,
        private readonly int $merchantId,
        private readonly Money $money,
        private readonly string $description,
        private readonly string $email,
        private readonly string $clientIp,
        TransactionStatus $initialStatus = TransactionStatus::CREATED
    ) {
        $this->status = $initialStatus;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Reconstruct an existing transaction from persistence storage without re-triggering domain lifecycle rules.
     */
    public static function reconstitute(
        string $id,
        string $sessionId,
        int $merchantId,
        Money $money,
        string $description,
        string $email,
        string $clientIp,
        TransactionStatus $status,
        ?string $token,
        ?string $paymentMethod,
        ?string $rejectionReason,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        $transaction = new self(
            id: $id,
            sessionId: $sessionId,
            merchantId: $merchantId,
            money: $money,
            description: $description,
            email: $email,
            clientIp: $clientIp,
            initialStatus: $status
        );
        $transaction->token = $token;
        $transaction->paymentMethod = $paymentMethod;
        $transaction->rejectionReason = $rejectionReason;
        $transaction->createdAt = $createdAt;
        $transaction->updatedAt = $updatedAt;

        return $transaction;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getMerchantId(): int
    {
        return $this->merchantId;
    }

    public function getMoney(): Money
    {
        return $this->money;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getClientIp(): string
    {
        return $this->clientIp;
    }

    public function getStatus(): TransactionStatus
    {
        return $this->status;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Mark session registered and generate payment gateway redirect token.
     */
    public function registerToken(string $token): void
    {
        $this->transitionTo(TransactionStatus::PENDING);
        $this->token = $token;
    }

    /**
     * Authorize funds at acquirer / bank.
     */
    public function authorize(string $paymentMethod): void
    {
        $this->transitionTo(TransactionStatus::AUTHORIZED);
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Capture / finalize funds.
     */
    public function capture(?string $paymentMethod = null): void
    {
        $this->transitionTo(TransactionStatus::CAPTURED);
        if ($paymentMethod !== null) {
            $this->paymentMethod = $paymentMethod;
        }
    }

    /**
     * Reject transaction due to error or insufficient funds.
     */
    public function reject(string $reason): void
    {
        $this->transitionTo(TransactionStatus::REJECTED);
        $this->rejectionReason = $reason;
    }

    /**
     * Refund captured transaction.
     */
    public function refund(): void
    {
        $this->transitionTo(TransactionStatus::REFUNDED);
    }

    private function transitionTo(TransactionStatus $target): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw InvalidStateTransitionException::create($this->status, $target);
        }

        $this->status = $target;
        $this->updatedAt = new DateTimeImmutable();
    }
}
