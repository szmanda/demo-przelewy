<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Handler;

use App\Domain\Event\PaymentCapturedEvent;
use App\Infrastructure\Elasticsearch\Repository\ElasticsearchTransactionRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ElasticsearchIndexerHandler
{
    public function __construct(
        private readonly ElasticsearchTransactionRepository $elasticsearchRepository,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function __invoke(PaymentCapturedEvent $event): void
    {
        $document = [
            'id' => $event->transactionId,
            'session_id' => $event->sessionId,
            'merchant_id' => $event->merchantId,
            'amount' => $event->amountInMinorUnits,
            'amount_formatted' => $event->amountInMinorUnits / 100.0,
            'currency' => $event->currency,
            'email' => $event->email,
            'client_ip' => $event->clientIp,
            'status' => 'CAPTURED',
            'payment_method' => $event->paymentMethod,
            'created_at' => $event->capturedAt->format('c'),
        ];

        try {
            $this->elasticsearchRepository->indexTransaction($document);
            $this->logger?->info('Transaction indexed into Elasticsearch', ['id' => $event->transactionId]);
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to index transaction into Elasticsearch', [
                'id' => $event->transactionId,
                'error' => $e->getMessage(),
            ]);
            throw $e; // Re-throw to trigger Messenger retry strategy & DLQ
        }
    }
}
