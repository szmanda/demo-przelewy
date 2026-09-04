<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Service;

use App\Application\Service\FraudDetectionService;
use App\Tests\Support\MockElasticsearchClientFactory;
use PHPUnit\Framework\TestCase;

final class FraudDetectionServiceTest extends TestCase
{
    private FraudDetectionService $service;

    protected function setUp(): void
    {
        $mockClient = MockElasticsearchClientFactory::create();
        $this->service = new FraudDetectionService($mockClient);
    }

    public function testEvaluateLowRiskTransaction(): void
    {
        $result = $this->service->evaluateRisk('127.0.0.1', 'good_user@example.com', 5000);

        $this->assertSame(0, $result['score']);
        $this->assertSame('LOW', $result['riskLevel']);
        $this->assertSame('ALLOW', $result['recommendation']);
        $this->assertEmpty($result['reasons']);
    }

    public function testEvaluateHighAmountTransactionScoresPoints(): void
    {
        // 12,000.00 PLN (1,200,000 minor units) -> exceeds 10,000 PLN threshold
        $result = $this->service->evaluateRisk('127.0.0.1', 'high_ticket@example.com', 1200000);

        $this->assertSame(20, $result['score']);
        $this->assertCount(1, $result['reasons']);
        $this->assertStringContainsString('10,000.00 PLN threshold', $result['reasons'][0]);
    }
}
