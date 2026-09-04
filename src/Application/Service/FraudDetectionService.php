<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Infrastructure\Elasticsearch\Index\TransactionIndexManager;
use Elastic\Elasticsearch\Client;
use Psr\Log\LoggerInterface;

final readonly class FraudDetectionService
{
    public function __construct(
        private Client $client,
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Evaluate transaction risk using real-time Elasticsearch velocity heuristics.
     *
     * @return array{
     *     score: int,
     *     riskLevel: string,
     *     recommendation: string,
     *     reasons: list<string>,
     *     metrics: array{ipCount1m: int, emailCount15m: int}
     * }
     */
    public function evaluateRisk(string $clientIp, string $email, int $amountInMinorUnits): array
    {
        $reasons = [];
        $score = 0;

        $ipCount1m = $this->countTransactionsByIpInTimeframe($clientIp, 'now-1m');
        $emailCount15m = $this->countTransactionsByEmailInTimeframe($email, 'now-15m');

        // Velocity Rule 1: High frequency from same IP
        if ($ipCount1m >= 5) {
            $score += 50;
            $reasons[] = sprintf('High IP velocity: %d attempts within 1 minute from %s.', $ipCount1m, $clientIp);
        } elseif ($ipCount1m >= 3) {
            $score += 25;
            $reasons[] = sprintf('Moderate IP velocity: %d attempts within 1 minute from %s.', $ipCount1m, $clientIp);
        }

        // Velocity Rule 2: Email card-testing frequency
        if ($emailCount15m >= 8) {
            $score += 40;
            $reasons[] = sprintf('High Email velocity: %d attempts within 15 minutes for %s.', $emailCount15m, $email);
        } elseif ($emailCount15m >= 4) {
            $score += 20;
            $reasons[] = sprintf('Moderate Email velocity: %d attempts within 15 minutes for %s.', $emailCount15m, $email);
        }

        // Velocity Rule 3: Abnormally high single transaction amount (e.g. > 10,000 PLN)
        if ($amountInMinorUnits > 1000000) {
            $score += 20;
            $reasons[] = 'High single transaction amount exceeding 10,000.00 PLN threshold.';
        }

        $riskLevel = match (true) {
            $score >= 60 => 'HIGH',
            $score >= 25 => 'MEDIUM',
            default => 'LOW',
        };

        $recommendation = match ($riskLevel) {
            'HIGH' => 'BLOCK',
            'MEDIUM' => 'CHALLENGE_3DS',
            'LOW' => 'ALLOW',
        };

        $this->logger?->info('Fraud risk evaluation completed', [
            'client_ip' => $clientIp,
            'email' => $email,
            'score' => $score,
            'risk_level' => $riskLevel,
            'recommendation' => $recommendation,
        ]);

        return [
            'score' => min(100, $score),
            'riskLevel' => $riskLevel,
            'recommendation' => $recommendation,
            'reasons' => $reasons,
            'metrics' => [
                'ipCount1m' => $ipCount1m,
                'emailCount15m' => $emailCount15m,
            ],
        ];
    }

    private function countTransactionsByIpInTimeframe(string $ip, string $since): int
    {
        try {
            $response = $this->client->count([
                'index' => TransactionIndexManager::ALIAS_NAME,
                'body' => [
                    'query' => [
                        'bool' => [
                            'filter' => [
                                ['term' => ['client_ip' => $ip]],
                                ['range' => ['created_at' => ['gte' => $since]]],
                            ],
                        ],
                    ],
                ],
            ]);

            return (int) ($response['count'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function countTransactionsByEmailInTimeframe(string $email, string $since): int
    {
        try {
            $response = $this->client->count([
                'index' => TransactionIndexManager::ALIAS_NAME,
                'body' => [
                    'query' => [
                        'bool' => [
                            'filter' => [
                                ['term' => ['email.keyword' => $email]],
                                ['range' => ['created_at' => ['gte' => $since]]],
                            ],
                        ],
                    ],
                ],
            ]);

            return (int) ($response['count'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
