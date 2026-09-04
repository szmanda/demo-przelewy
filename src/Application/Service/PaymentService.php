<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\BlikAuthorizeRequest;
use App\Application\DTO\NotificationRequest;
use App\Application\DTO\RegisterPaymentRequest;
use App\Domain\Event\PaymentCapturedEvent;
use App\Domain\Model\Money;
use App\Domain\Model\Transaction;
use App\Domain\Repository\TransactionRepositoryInterface;
use App\Domain\Security\SignatureCalculator;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class PaymentService
{
    public function __construct(
        private readonly TransactionRepositoryInterface $repository,
        private readonly SignatureCalculator $signatureCalculator,
        private readonly MessageBusInterface $messageBus
    ) {
    }

    /**
     * Register a new transaction session or return existing idempotent session.
     */
    public function registerTransaction(RegisterPaymentRequest $request, string $crcKey): Transaction
    {
        // 1. Verify cryptographic signature
        $expectedSignature = $this->signatureCalculator->calculateForRegistration(
            $request->sessionId,
            $request->merchantId,
            $request->amount,
            $request->currency,
            $crcKey
        );
        $this->signatureCalculator->verify($expectedSignature, $request->signature);

        // 2. Check idempotency
        $existing = $this->repository->findBySessionIdAndMerchantId($request->sessionId, $request->merchantId);
        if ($existing !== null) {
            return $existing;
        }

        // 3. Create new domain transaction aggregate
        $transaction = new Transaction(
            id: Uuid::v4()->toRfc4122(),
            sessionId: $request->sessionId,
            merchantId: $request->merchantId,
            money: new Money($request->amount, $request->currency),
            description: $request->description,
            email: $request->email,
            clientIp: $request->clientIp
        );

        // 4. Generate gateway redirect token
        $token = 'p24_token_' . bin2hex(random_bytes(16));
        $transaction->registerToken($token);

        // 5. Persist
        $this->repository->save($transaction);

        return $transaction;
    }

    /**
     * Process direct BLIK 6-digit one-time code authorization.
     */
    public function authorizeBlik(BlikAuthorizeRequest $request): Transaction
    {
        $transaction = $this->repository->findBySessionIdAndMerchantId($request->sessionId, $request->merchantId);
        if ($transaction === null) {
            throw new RuntimeException(sprintf('Transaction with sessionId "%s" not found.', $request->sessionId));
        }

        // Simulate BLIK network response codes:
        if (str_starts_with($request->blikCode, '777')) {
            $transaction->reject('BLIK user rejected authorization in banking app.');
            $this->repository->save($transaction);
            return $transaction;
        }

        if (str_starts_with($request->blikCode, '999')) {
            $transaction->reject('BLIK banking app confirmation timeout.');
            $this->repository->save($transaction);
            return $transaction;
        }

        // Authorize & Capture
        $transaction->authorize('BLIK');
        $transaction->capture();
        $this->repository->save($transaction);

        // Publish event to RabbitMQ
        $this->messageBus->dispatch(new PaymentCapturedEvent(
            transactionId: $transaction->getId(),
            sessionId: $transaction->getSessionId(),
            merchantId: $transaction->getMerchantId(),
            amountInMinorUnits: $transaction->getMoney()->amountInMinorUnits,
            currency: $transaction->getMoney()->currency,
            email: $transaction->getEmail(),
            clientIp: $transaction->getClientIp(),
            paymentMethod: 'BLIK',
            capturedAt: new DateTimeImmutable()
        ));

        return $transaction;
    }

    /**
     * Process bank / acquirer webhook notification.
     */
    public function processNotification(NotificationRequest $request, string $crcKey): Transaction
    {
        // 1. Verify notification signature
        $expectedSignature = $this->signatureCalculator->calculateForNotification(
            $request->sessionId,
            $request->orderId,
            $request->amount,
            $request->currency,
            $crcKey
        );
        $this->signatureCalculator->verify($expectedSignature, $request->signature);

        // 2. Locate transaction
        $transaction = $this->repository->findBySessionIdAndMerchantId($request->sessionId, $request->merchantId);
        if ($transaction === null) {
            throw new RuntimeException(sprintf('Transaction with sessionId "%s" not found.', $request->sessionId));
        }

        // 3. Capture transaction
        $transaction->capture($request->paymentMethod);
        $this->repository->save($transaction);

        // 4. Publish async event via RabbitMQ
        $this->messageBus->dispatch(new PaymentCapturedEvent(
            transactionId: $transaction->getId(),
            sessionId: $transaction->getSessionId(),
            merchantId: $transaction->getMerchantId(),
            amountInMinorUnits: $transaction->getMoney()->amountInMinorUnits,
            currency: $transaction->getMoney()->currency,
            email: $transaction->getEmail(),
            clientIp: $transaction->getClientIp(),
            paymentMethod: (string) $transaction->getPaymentMethod(),
            capturedAt: new DateTimeImmutable()
        ));

        return $transaction;
    }
}
