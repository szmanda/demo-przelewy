<?php

declare(strict_types=1);

namespace App\UI\Cli;

use App\Domain\Model\Money;
use App\Domain\Model\Transaction;
use App\Domain\Model\TransactionStatus;
use App\Domain\Repository\TransactionRepositoryInterface;
use App\Infrastructure\Elasticsearch\Repository\ElasticsearchTransactionRepository;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:payment:seed',
    description: 'Seeds realistic mock transactions across merchants, BLIK, Cards, and PBL.'
)]
final class SeedTransactionsCommand extends Command
{
    private const MERCHANTS = [100234, 100500, 200100, 300450];
    private const METHODS = ['BLIK', 'CARD', 'PBL_MBANK', 'PBL_SANTANDER', 'APPLE_PAY', 'GOOGLE_PAY'];
    private const EMAILS = [
        'anna.kowalska@example.com',
        'jan.nowak@example.com',
        'piotr.zielinski@example.com',
        'katarzyna.wisniewska@example.com',
        'tomasz.wojcik@example.com',
    ];
    private const IPS = ['195.150.9.37', '83.24.120.5', '178.43.20.91', '31.0.124.8'];

    public function __construct(
        private readonly TransactionRepositoryInterface $repository,
        private readonly ?ElasticsearchTransactionRepository $elasticsearchRepository = null
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('count', null, InputOption::VALUE_OPTIONAL, 'Number of transactions to generate', '50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = (int) $input->getOption('count');
        $io->title(sprintf('Seeding %d Demo Transactions', $count));

        $io->progressStart($count);

        for ($i = 0; $i < $count; ++$i) {
            $merchantId = self::MERCHANTS[array_rand(self::MERCHANTS)];
            $method = self::METHODS[array_rand(self::METHODS)];
            $email = self::EMAILS[array_rand(self::EMAILS)];
            $ip = self::IPS[array_rand(self::IPS)];
            $amount = random_int(1500, 150000); // 15.00 PLN to 1,500.00 PLN
            $sessionId = sprintf('sess_seed_%s_%d', bin2hex(random_bytes(4)), $i);
            $id = Uuid::v4()->toRfc4122();

            $statuses = [
                TransactionStatus::CAPTURED,
                TransactionStatus::CAPTURED,
                TransactionStatus::CAPTURED,
                TransactionStatus::PENDING,
                TransactionStatus::REJECTED,
                TransactionStatus::REFUNDED,
            ];
            $status = $statuses[array_rand($statuses)];

            $transaction = new Transaction(
                id: $id,
                sessionId: $sessionId,
                merchantId: $merchantId,
                money: new Money($amount, 'PLN'),
                description: sprintf('Order #%d online store purchase', 1000 + $i),
                email: $email,
                clientIp: $ip,
                initialStatus: $status
            );

            // Set reflection fields
            $reflection = new \ReflectionClass($transaction);
            $tokenProp = $reflection->getProperty('token');
            $tokenProp->setAccessible(true);
            $tokenProp->setValue($transaction, 'p24_token_' . bin2hex(random_bytes(16)));

            $methodProp = $reflection->getProperty('paymentMethod');
            $methodProp->setAccessible(true);
            $methodProp->setValue($transaction, $method);

            // Distribute timestamps over last 48 hours
            $hoursAgo = random_int(0, 48);
            $minutesAgo = random_int(0, 59);
            $createdAt = (new DateTimeImmutable())->modify(sprintf('-%d hours -%d minutes', $hoursAgo, $minutesAgo));
            $createdProp = $reflection->getProperty('createdAt');
            $createdProp->setAccessible(true);
            $createdProp->setValue($transaction, $createdAt);

            $this->repository->save($transaction);

            // Index in Elasticsearch
            if ($this->elasticsearchRepository !== null) {
                try {
                    $this->elasticsearchRepository->indexTransaction([
                        'id' => $id,
                        'session_id' => $sessionId,
                        'merchant_id' => $merchantId,
                        'amount' => $amount,
                        'amount_formatted' => $amount / 100.0,
                        'currency' => 'PLN',
                        'description' => sprintf('Order #%d online store purchase', 1000 + $i),
                        'email' => $email,
                        'client_ip' => $ip,
                        'status' => $status->value,
                        'payment_method' => $method,
                        'created_at' => $createdAt->format('c'),
                    ]);
                } catch (\Throwable) {
                    // Ignore if ES is not ready during standalone runs
                }
            }

            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success(sprintf('Successfully created %d sample transactions.', $count));

        return Command::SUCCESS;
    }
}
