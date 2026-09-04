<?php

declare(strict_types=1);

namespace App\UI\Cli;

use App\Infrastructure\Elasticsearch\Index\TransactionIndexManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:elasticsearch:setup',
    description: 'Creates the Elasticsearch index mapping and alias for transactions.'
)]
final class SetupElasticsearchCommand extends Command
{
    public function __construct(
        private readonly TransactionIndexManager $indexManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Elasticsearch Index Setup');

        try {
            $this->indexManager->createIndexIfNotExists();
            $io->success(sprintf(
                'Elasticsearch index "%s" with alias "%s" is ready.',
                TransactionIndexManager::INDEX_NAME,
                TransactionIndexManager::ALIAS_NAME
            ));
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Failed to initialize Elasticsearch index: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
