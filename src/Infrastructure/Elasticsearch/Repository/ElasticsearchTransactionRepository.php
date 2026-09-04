<?php

declare(strict_types=1);

namespace App\Infrastructure\Elasticsearch\Repository;

use App\Infrastructure\Elasticsearch\Index\TransactionIndexManager;
use Elastic\Elasticsearch\Client;

final class ElasticsearchTransactionRepository
{
    public function __construct(
        private readonly Client $client
    ) {
    }

    /**
     * @param array<string, mixed> $document
     */
    public function indexTransaction(array $document): void
    {
        $this->client->index([
            'index' => TransactionIndexManager::ALIAS_NAME,
            'id' => (string) $document['id'],
            'body' => $document,
            'refresh' => 'true', // Ensure immediate visibility for tests and queries
        ]);
    }

    /**
     * Search transactions with rich query filters.
     *
     * @param array{
     *     merchant_id?: int,
     *     status?: string,
     *     payment_method?: string,
     *     query?: string,
     *     min_amount?: int,
     *     max_amount?: int,
     *     from_date?: string,
     *     to_date?: string,
     *     page?: int,
     *     limit?: int
     * } $criteria
     * @return array<string, mixed>
     */
    public function search(array $criteria): array
    {
        $must = [];
        $filter = [];

        if (!empty($criteria['merchant_id'])) {
            $filter[] = ['term' => ['merchant_id' => (int) $criteria['merchant_id']]];
        }

        if (!empty($criteria['status'])) {
            $filter[] = ['term' => ['status' => strtoupper((string) $criteria['status'])]];
        }

        if (!empty($criteria['payment_method'])) {
            $filter[] = ['term' => ['payment_method' => strtoupper((string) $criteria['payment_method'])]];
        }

        if (!empty($criteria['query'])) {
            $must[] = [
                'multi_match' => [
                    'query' => (string) $criteria['query'],
                    'fields' => ['session_id', 'email', 'description^2'],
                ],
            ];
        }

        if (isset($criteria['min_amount']) || isset($criteria['max_amount'])) {
            $range = [];
            if (isset($criteria['min_amount'])) {
                $range['gte'] = (int) $criteria['min_amount'];
            }
            if (isset($criteria['max_amount'])) {
                $range['lte'] = (int) $criteria['max_amount'];
            }
            $filter[] = ['range' => ['amount' => $range]];
        }

        if (!empty($criteria['from_date']) || !empty($criteria['to_date'])) {
            $dateRange = [];
            if (!empty($criteria['from_date'])) {
                $dateRange['gte'] = (string) $criteria['from_date'];
            }
            if (!empty($criteria['to_date'])) {
                $dateRange['lte'] = (string) $criteria['to_date'];
            }
            $filter[] = ['range' => ['created_at' => $dateRange]];
        }

        $page = max(1, (int) ($criteria['page'] ?? 1));
        $limit = max(1, min(100, (int) ($criteria['limit'] ?? 20)));
        $from = ($page - 1) * $limit;

        $body = [
            'query' => [
                'bool' => array_filter([
                    'must' => empty($must) ? ['match_all' => new \stdClass()] : $must,
                    'filter' => $filter,
                ]),
            ],
            'sort' => [
                ['created_at' => ['order' => 'desc']],
            ],
            'from' => $from,
            'size' => $limit,
        ];

        $response = $this->client->search([
            'index' => TransactionIndexManager::ALIAS_NAME,
            'body' => $body,
        ]);

        $hits = $response['hits']['hits'] ?? [];
        $total = $response['hits']['total']['value'] ?? 0;

        $results = array_map(static fn(array $hit) => $hit['_source'], $hits);

        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'items' => $results,
        ];
    }

    /**
     * Compute Real-time GMV (Gross Merchandise Value) and conversion metrics using Elasticsearch aggregations.
     *
     * @return array<string, mixed>
     */
    public function getGmvAnalytics(int $merchantId, string $timeframe = 'now-24h'): array
    {
        $body = [
            'size' => 0,
            'query' => [
                'bool' => [
                    'filter' => [
                        ['term' => ['merchant_id' => $merchantId]],
                        ['term' => ['status' => 'CAPTURED']],
                        ['range' => ['created_at' => ['gte' => $timeframe]]],
                    ],
                ],
            ],
            'aggs' => [
                'total_volume' => [
                    'sum' => ['field' => 'amount_formatted'],
                ],
                'volume_by_payment_method' => [
                    'terms' => ['field' => 'payment_method'],
                    'aggs' => [
                        'sum_amount' => ['sum' => ['field' => 'amount_formatted']],
                    ],
                ],
                'hourly_volume' => [
                    'date_histogram' => [
                        'field' => 'created_at',
                        'calendar_interval' => '1h',
                    ],
                    'aggs' => [
                        'sum_amount' => ['sum' => ['field' => 'amount_formatted']],
                    ],
                ],
            ],
        ];

        $response = $this->client->search([
            'index' => TransactionIndexManager::ALIAS_NAME,
            'body' => $body,
        ]);

        return [
            'total_volume' => $response['aggregations']['total_volume']['value'] ?? 0.0,
            'by_payment_method' => $response['aggregations']['volume_by_payment_method']['buckets'] ?? [],
            'hourly' => $response['aggregations']['hourly_volume']['buckets'] ?? [],
        ];
    }
}
