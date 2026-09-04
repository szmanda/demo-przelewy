<?php

declare(strict_types=1);

namespace App\UI\Cli;

use App\Infrastructure\Elasticsearch\Repository\ElasticsearchTransactionRepository;
use App\Infrastructure\Persistence\Doctrine\Entity\TransactionEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:elasticsearch:reindex',
    description: 'Bulk re-indexes all transactions from PostgreSQL into Elasticsearch.'
)]
final class ReindexTransactionsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ElasticsearchTransactionRepository $elasticsearchRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Re-indexing Transactions into Elasticsearch');

        $repo = $this->entityManager->getRepository(TransactionEntity::class);
        $entities = $repo->findAll();

        if (empty($entities)) {
            $io->warning('No transactions found in database to index.');
            return Command::SUCCESS;
        }

        $io->progressStart(count($entities));

        foreach ($entities as $entity) {
            $domain = $entity->toDomain();
            $this->elasticsearchRepository->indexTransaction([
                'id' => $domain->getId(),
                'session_id' => $domain->getSessionId(),
                'merchant_id' => $domain->getMerchantId(),
                'amount' => $domain->getMoney()->amountInMinorUnits,
                'amount_formatted' => $domain->getMoney()->toMajorUnits(),
                'currency' => $domain->getMoney()->currency,
                'description' => $domain->getDescription(),
                'email' => $domain->getEmail(),
                'client_ip' => $domain->getClientIp(),
                'status' => $domain->getStatus()->value,
                'payment_method' => $domain->getPaymentMethod() ?? 'UNKNOWN',
                'created_at' => $domain->getCreatedAt()->format('c'),
            ]);
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success(sprintf('Successfully indexed %d transactions into Elasticsearch.', count($entities)));

        return Command::SUCCESS;
    }
}
