<?php

declare(strict_types=1);

namespace App\Infrastructure\Elasticsearch\Index;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;

final class TransactionIndexManager
{
    public const INDEX_NAME = 'transactions_v1';
    public const ALIAS_NAME = 'transactions';

    public function __construct(
        private readonly Client $client
    ) {
    }

    public function createIndexIfNotExists(): void
    {
        try {
            $exists = $this->client->indices()->exists(['index' => self::INDEX_NAME])->asBool();
            if ($exists) {
                return;
            }
        } catch (ClientResponseException) {
            // Proceed to create
        }

        $params = [
            'index' => self::INDEX_NAME,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'analyzer' => [
                            'email_analyzer' => [
                                'type' => 'custom',
                                'tokenizer' => 'uax_url_email',
                                'filter' => ['lowercase'],
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'keyword'],
                        'session_id' => ['type' => 'keyword'],
                        'merchant_id' => ['type' => 'integer'],
                        'amount' => ['type' => 'long'],
                        'amount_formatted' => ['type' => 'double'],
                        'currency' => ['type' => 'keyword'],
                        'description' => ['type' => 'text'],
                        'email' => [
                            'type' => 'text',
                            'analyzer' => 'email_analyzer',
                            'fields' => [
                                'keyword' => ['type' => 'keyword'],
                            ],
                        ],
                        'client_ip' => ['type' => 'ip'],
                        'status' => ['type' => 'keyword'],
                        'payment_method' => ['type' => 'keyword'],
                        'created_at' => [
                            'type' => 'date',
                            'format' => 'strict_date_time||epoch_millis',
                        ],
                    ],
                ],
                'aliases' => [
                    self::ALIAS_NAME => new \stdClass(),
                ],
            ],
        ];

        $this->client->indices()->create($params);
    }
}
