<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Http\Message\ResponseFactory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class MockElasticsearchClientFactory
{
    public static function create(): Client
    {
        $mockHttpClient = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $body = json_encode([
                    'acknowledged' => true,
                    'hits' => [
                        'total' => ['value' => 1],
                        'hits' => [
                            [
                                '_id' => 'sample-id',
                                '_source' => [
                                    'id' => 'sample-id',
                                    'session_id' => 'sess_sample',
                                    'merchant_id' => 100234,
                                    'amount' => 10000,
                                    'amount_formatted' => 100.0,
                                    'status' => 'CAPTURED',
                                    'payment_method' => 'BLIK',
                                    'created_at' => (new \DateTimeImmutable())->format('c'),
                                ],
                            ],
                        ],
                    ],
                    'aggregations' => [
                        'total_volume' => ['value' => 100.0],
                        'volume_by_payment_method' => ['buckets' => []],
                        'hourly_volume' => ['buckets' => []],
                    ],
                ]);

                return new Response(200, ['Content-Type' => 'application/json'], (string) $body);
            }
        };

        return ClientBuilder::create()
            ->setHttpClient($mockHttpClient)
            ->build();
    }
}
