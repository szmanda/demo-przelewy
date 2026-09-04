# Payment Gateway PoC Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a robust, test-covered Payment Gateway reference implementation demonstrating RabbitMQ, Elasticsearch, PHPUnit, Codeception, and PostgreSQL in Symfony 7 following Clean Architecture.

**Architecture:** Domain-driven core (aggregates, state machine, value objects, cryptographic signature checks) decoupled from infrastructure (Doctrine ORM, Redis locks, RabbitMQ messenger, Elasticsearch search/analytics).

**Tech Stack:** PHP 8.2+, Symfony 7, PostgreSQL 16, Redis 7, RabbitMQ 3, Elasticsearch 8.15, PHPUnit 11, Codeception.

---

### Task 1: Dependency Setup & Configuration
- Set up `composer.json` with Codeception, PHPUnit, Elasticsearch SDK, Doctrine, and Symfony Messenger.
- Run composer install / verify lockfile.
- Configure Codeception (`codeception.yml`, `tests/Support/ApiTester.php`, `tests/Api.suite.yml`).

### Task 2: Pure Domain Layer (DDD / SOLID)
- `src/Domain/Model/Money.php`: Value object with currency and integer minor units (e.g. grosze/cents).
- `src/Domain/Model/TransactionStatus.php`: Enum representing payment state machine (`CREATED`, `PENDING`, `AUTHORIZED`, `CAPTURED`, `FAILED`, `REFUNDED`).
- `src/Domain/Model/Transaction.php`: Domain aggregate root enforcing valid state transitions.
- `src/Domain/Security/SignatureCalculator.php`: Calculates & verifies SHA-384 / CRC integrity hashes.
- `src/Domain/Exception/InvalidStateTransitionException.php` and `InvalidSignatureException.php`.

### Task 3: Domain Unit Tests (PHPUnit)
- `tests/Unit/Domain/Model/TransactionTest.php`: Tests state machine transitions and invalid transition guards.
- `tests/Unit/Domain/Model/MoneyTest.php`: Precision, formatting, and currency operations.
- `tests/Unit/Domain/Security/SignatureCalculatorTest.php`: Verifies cryptographic checksum matching Przelewy24 specs.
- Verify tests pass with `vendor/bin/phpunit`.

### Task 4: Infrastructure & Persistence
- `src/Infrastructure/Persistence/Doctrine/Entity/TransactionEntity.php` or ORM mapping.
- `src/Infrastructure/Persistence/Doctrine/DoctrineTransactionRepository.php`.
- `src/Domain/Repository/TransactionRepositoryInterface.php`.
- Database schema migration / Doctrine config.

### Task 5: RabbitMQ & Asynchronous Messaging
- `src/Domain/Event/PaymentCapturedEvent.php`.
- `config/packages/messenger.yaml`: Routing `PaymentCapturedEvent` to RabbitMQ AMQP transport.
- `src/Infrastructure/Messaging/Handler/ElasticsearchIndexerHandler.php`: Consumes event and indexes into Elasticsearch.
- `src/Infrastructure/Messaging/Handler/MerchantWebhookDispatcherHandler.php`: Simulates reliable merchant notification dispatch.

### Task 6: Elasticsearch Service & Analytics
- `src/Infrastructure/Elasticsearch/ElasticsearchClientFactory.php`: Creates configured ES 8 client.
- `src/Infrastructure/Elasticsearch/Index/TransactionIndexManager.php`: Creates `transactions_v1` index with mappings.
- `src/Infrastructure/Elasticsearch/Repository/ElasticsearchTransactionRepository.php`: Search query builder (multi-match, status filter, date ranges) and GMV aggregation query.

### Task 7: Application & REST API Controllers
- `src/Application/Service/PaymentService.php`: Orchestrates registration, idempotency checking with Redis, and notification processing.
- `src/UI/Http/Rest/PaymentController.php`:
  - `POST /api/v1/payments/register` (registers session, checks idempotency, calculates signature).
  - `POST /api/v1/payments/notify` (bank callback, verifies signature, advances state to `CAPTURED`, publishes to RabbitMQ).
  - `GET /api/v1/payments/search` (searches Elasticsearch).
  - `GET /api/v1/analytics/gmv` (GMV aggregations by hour/payment method).

### Task 8: Codeception API / Acceptance Suite
- Configure Codeception REST module.
- `tests/Api/PaymentCest.php`:
  - `testRegisterPaymentSuccessfully`
  - `testIdempotentRegistrationReturnsSameSession`
  - `testRejectNotificationWithInvalidSignature`
  - `testProcessValidNotificationAndCapturePayment`
- Run and verify Codeception suite.

### Task 9: Verification, Makefile Integration & Walkthrough
- Run full PHPUnit test suite.
- Run full Codeception test suite.
- Verify Docker compose services and endpoints.
- Update walkthrough documentation.
