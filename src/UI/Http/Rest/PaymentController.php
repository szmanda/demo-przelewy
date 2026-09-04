<?php

declare(strict_types=1);

namespace App\UI\Http\Rest;

use App\Application\DTO\BlikAuthorizeRequest;
use App\Application\DTO\NotificationRequest;
use App\Application\DTO\RegisterPaymentRequest;
use App\Application\Service\FraudDetectionService;
use App\Application\Service\PaymentService;
use App\Domain\Exception\InvalidSignatureException;
use App\Domain\Repository\TransactionRepositoryInterface;
use App\Infrastructure\Elasticsearch\Repository\ElasticsearchTransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[Route('/api/v1')]
final class PaymentController extends AbstractController
{
    private const DEFAULT_CRC_KEY = 'crc_secret_demo_key_998877';

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly ?ElasticsearchTransactionRepository $elasticsearchRepository = null,
        private readonly ?FraudDetectionService $fraudDetectionService = null
    ) {
    }

    #[Route('/payments/register', name: 'api_payment_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $dto = RegisterPaymentRequest::fromArray($payload);
            $crcKey = (string) $request->headers->get('X-Merchant-Crc-Key', self::DEFAULT_CRC_KEY);

            $transaction = $this->paymentService->registerTransaction($dto, $crcKey);

            return $this->json([
                'status' => 'success',
                'data' => [
                    'id' => $transaction->getId(),
                    'sessionId' => $transaction->getSessionId(),
                    'token' => $transaction->getToken(),
                    'status' => $transaction->getStatus()->value,
                    'redirectUrl' => 'https://secure.przelewy24.pl/trnRequest/' . $transaction->getToken(),
                ],
            ], Response::HTTP_CREATED);
        } catch (InvalidSignatureException $e) {
            return $this->json(['error' => 'Signature verification failed: ' . $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        } catch (Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/payments/blik/authorize', name: 'api_payment_blik_authorize', methods: ['POST'])]
    public function authorizeBlik(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $dto = BlikAuthorizeRequest::fromArray($payload);
            $transaction = $this->paymentService->authorizeBlik($dto);

            return $this->json([
                'status' => $transaction->getStatus()->value === 'CAPTURED' ? 'SUCCESS' : 'FAILED',
                'data' => [
                    'id' => $transaction->getId(),
                    'sessionId' => $transaction->getSessionId(),
                    'status' => $transaction->getStatus()->value,
                    'rejectionReason' => $transaction->getRejectionReason(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/payments/notify', name: 'api_payment_notify', methods: ['POST'])]
    public function notify(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $dto = NotificationRequest::fromArray($payload);
            $crcKey = (string) $request->headers->get('X-Merchant-Crc-Key', self::DEFAULT_CRC_KEY);

            $transaction = $this->paymentService->processNotification($dto, $crcKey);

            return $this->json([
                'status' => 'OK',
                'data' => [
                    'id' => $transaction->getId(),
                    'sessionId' => $transaction->getSessionId(),
                    'status' => $transaction->getStatus()->value,
                    'paymentMethod' => $transaction->getPaymentMethod(),
                ],
            ], Response::HTTP_OK);
        } catch (InvalidSignatureException $e) {
            return $this->json(['error' => 'Signature verification failed: ' . $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        } catch (Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/payments/{merchantId}/{sessionId}', name: 'api_payment_status', methods: ['GET'])]
    public function getStatus(int $merchantId, string $sessionId): JsonResponse
    {
        $transaction = $this->transactionRepository->findBySessionIdAndMerchantId($sessionId, $merchantId);
        if ($transaction === null) {
            return $this->json(['error' => 'Transaction not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $transaction->getId(),
            'sessionId' => $transaction->getSessionId(),
            'merchantId' => $transaction->getMerchantId(),
            'amount' => $transaction->getMoney()->amountInMinorUnits,
            'currency' => $transaction->getMoney()->currency,
            'status' => $transaction->getStatus()->value,
            'paymentMethod' => $transaction->getPaymentMethod(),
            'token' => $transaction->getToken(),
            'createdAt' => $transaction->getCreatedAt()->format('c'),
            'updatedAt' => $transaction->getUpdatedAt()->format('c'),
        ]);
    }

    #[Route('/payments/search', name: 'api_payment_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        if ($this->elasticsearchRepository === null) {
            return $this->json(['error' => 'Elasticsearch service not available'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $criteria = [
            'merchant_id' => $request->query->getInt('merchant_id'),
            'status' => $request->query->getString('status'),
            'payment_method' => $request->query->getString('payment_method'),
            'query' => $request->query->getString('query'),
            'min_amount' => $request->query->has('min_amount') ? $request->query->getInt('min_amount') : null,
            'max_amount' => $request->query->has('max_amount') ? $request->query->getInt('max_amount') : null,
            'from_date' => $request->query->getString('from_date'),
            'to_date' => $request->query->getString('to_date'),
            'page' => $request->query->getInt('page', 1),
            'limit' => $request->query->getInt('limit', 20),
        ];

        try {
            $results = $this->elasticsearchRepository->search(array_filter($criteria, static fn($v) => $v !== null && $v !== '' && $v !== 0));
            return $this->json($results);
        } catch (Throwable $e) {
            return $this->json(['error' => 'Elasticsearch search error: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/fraud/check', name: 'api_fraud_check', methods: ['POST'])]
    public function checkFraud(Request $request): JsonResponse
    {
        if ($this->fraudDetectionService === null) {
            return $this->json(['error' => 'Fraud detection service not available'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        $clientIp = (string) ($payload['clientIp'] ?? '127.0.0.1');
        $email = (string) ($payload['email'] ?? '');
        $amount = (int) ($payload['amount'] ?? 0);

        $result = $this->fraudDetectionService->evaluateRisk($clientIp, $email, $amount);

        return $this->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    #[Route('/analytics/gmv', name: 'api_analytics_gmv', methods: ['GET'])]
    public function gmvAnalytics(Request $request): JsonResponse
    {
        if ($this->elasticsearchRepository === null) {
            return $this->json(['error' => 'Elasticsearch service not available'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $merchantId = $request->query->getInt('merchant_id', 100234);
        $timeframe = $request->query->getString('timeframe', 'now-24h');

        try {
            $data = $this->elasticsearchRepository->getGmvAnalytics($merchantId, $timeframe);
            return $this->json($data);
        } catch (Throwable $e) {
            return $this->json(['error' => 'Elasticsearch analytics error: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
