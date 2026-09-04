<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Domain\Security\SignatureCalculator;
use App\Tests\ApiTester;

final class PaymentCest
{
    private const CRC_KEY = 'crc_secret_demo_key_998877';
    private SignatureCalculator $signatureCalculator;

    public function _before(ApiTester $I): void
    {
        $this->signatureCalculator = new SignatureCalculator('sha384');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
    }

    public function testRegisterPaymentSuccessfully(ApiTester $I): void
    {
        $sessionId = 'sess_' . bin2hex(random_bytes(8));
        $merchantId = 100234;
        $amount = 14999;
        $currency = 'PLN';

        $signature = $this->signatureCalculator->calculateForRegistration(
            $sessionId,
            $merchantId,
            $amount,
            $currency,
            self::CRC_KEY
        );

        $I->sendPost('/api/v1/payments/register', [
            'sessionId' => $sessionId,
            'merchantId' => $merchantId,
            'amount' => $amount,
            'currency' => $currency,
            'description' => 'Online Store Order #1002',
            'email' => 'buyer@example.com',
            'clientIp' => '195.150.9.37',
            'signature' => $signature,
        ]);

        $I->seeResponseCodeIs(201);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'status' => 'success',
            'data' => [
                'sessionId' => $sessionId,
                'status' => 'PENDING',
            ],
        ]);
        $I->seeResponseJsonMatchesJsonPath('$.data.token');
        $I->seeResponseJsonMatchesJsonPath('$.data.redirectUrl');
    }

    public function testIdempotencyReturnsExistingSession(ApiTester $I): void
    {
        $sessionId = 'sess_idempotent_' . bin2hex(random_bytes(6));
        $merchantId = 100234;
        $amount = 9900;
        $currency = 'PLN';

        $signature = $this->signatureCalculator->calculateForRegistration(
            $sessionId,
            $merchantId,
            $amount,
            $currency,
            self::CRC_KEY
        );

        $payload = [
            'sessionId' => $sessionId,
            'merchantId' => $merchantId,
            'amount' => $amount,
            'currency' => $currency,
            'description' => 'Idempotent Order',
            'email' => 'idempotent@example.com',
            'clientIp' => '127.0.0.1',
            'signature' => $signature,
        ];

        // First call
        $I->sendPost('/api/v1/payments/register', $payload);
        $I->seeResponseCodeIs(201);
        $firstToken = $I->grabDataFromResponseByJsonPath('$.data.token')[0];

        // Second call with same sessionId and merchantId
        $I->sendPost('/api/v1/payments/register', $payload);
        $I->seeResponseCodeIs(201);
        $secondToken = $I->grabDataFromResponseByJsonPath('$.data.token')[0];

        $I->assertSame($firstToken, $secondToken);
    }

    public function testRejectInvalidSignature(ApiTester $I): void
    {
        $I->sendPost('/api/v1/payments/register', [
            'sessionId' => 'sess_tampered_' . bin2hex(random_bytes(4)),
            'merchantId' => 100234,
            'amount' => 5000,
            'currency' => 'PLN',
            'description' => 'Tampered Order',
            'email' => 'fraud@example.com',
            'clientIp' => '127.0.0.1',
            'signature' => 'invalid_tampered_signature_hash',
        ]);

        $I->seeResponseCodeIs(401);
        $I->seeResponseIsJson();
        $I->seeResponseContains('Signature verification failed');
    }

    public function testProcessBankNotificationAndCapture(ApiTester $I): void
    {
        $sessionId = 'sess_flow_' . bin2hex(random_bytes(6));
        $merchantId = 100234;
        $amount = 35000;
        $currency = 'PLN';
        $orderId = 987654;

        // 1. Register Payment
        $regSig = $this->signatureCalculator->calculateForRegistration(
            $sessionId,
            $merchantId,
            $amount,
            $currency,
            self::CRC_KEY
        );

        $I->sendPost('/api/v1/payments/register', [
            'sessionId' => $sessionId,
            'merchantId' => $merchantId,
            'amount' => $amount,
            'currency' => $currency,
            'description' => 'Cart Checkout',
            'email' => 'buyer_flow@example.com',
            'clientIp' => '127.0.0.1',
            'signature' => $regSig,
        ]);
        $I->seeResponseCodeIs(201);

        // 2. Simulate Bank Notification (IPN callback)
        $notifSig = $this->signatureCalculator->calculateForNotification(
            $sessionId,
            $orderId,
            $amount,
            $currency,
            self::CRC_KEY
        );

        $I->sendPost('/api/v1/payments/notify', [
            'sessionId' => $sessionId,
            'merchantId' => $merchantId,
            'orderId' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
            'paymentMethod' => 'BLIK',
            'signature' => $notifSig,
        ]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'status' => 'OK',
            'data' => [
                'sessionId' => $sessionId,
                'status' => 'CAPTURED',
                'paymentMethod' => 'BLIK',
            ],
        ]);

        // 3. Verify status endpoint
        $I->sendGet(sprintf('/api/v1/payments/%d/%s', $merchantId, $sessionId));
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'sessionId' => $sessionId,
            'status' => 'CAPTURED',
            'paymentMethod' => 'BLIK',
            'amount' => $amount,
            'currency' => $currency,
        ]);
    }
}
