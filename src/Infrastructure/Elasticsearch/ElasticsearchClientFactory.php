<?php

declare(strict_types=1);

namespace App\Infrastructure\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

final class ElasticsearchClientFactory
{
    public static function createClient(string $elasticsearchUrl): Client
    {
        return ClientBuilder::create()
            ->setHosts([$elasticsearchUrl])
            ->build();
    }
}
