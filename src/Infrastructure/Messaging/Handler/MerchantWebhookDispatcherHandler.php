<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Handler;

use App\Domain\Event\PaymentCapturedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MerchantWebhookDispatcherHandler
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function __invoke(PaymentCapturedEvent $event): void
    {
        $this->logger?->info('Dispatching merchant payment capture webhook notification', [
            'merchant_id' => $event->merchantId,
            'session_id' => $event->sessionId,
            'status' => 'CAPTURED',
        ]);

        // Simulates async HTTP notification dispatch with retry / DLQ support
    }
}
