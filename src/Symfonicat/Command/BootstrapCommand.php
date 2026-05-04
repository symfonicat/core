<?php

namespace Symfonicat\Command;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'symfonicat:bootstrap',
    description: 'Wait for the database and synchronize the schema',
)]
final class BootstrapCommand extends Command
{
    private const BOOTSTRAP_LOCK_NAMESPACE = 1398361414; // "SYMF"
    private const BOOTSTRAP_LOCK_KEY = 1112493908; // "BOOT"

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('wait', null, InputOption::VALUE_REQUIRED, 'Seconds to wait for the database to become available.', '60')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $waitSeconds = max(0, (int) $input->getOption('wait'));

        if (!$this->waitForDatabase($waitSeconds)) {
            $io->error(sprintf('Database did not become available within %d seconds.', $waitSeconds));

            return Command::FAILURE;
        }

        $this->runWithBootstrapLock(function () use ($input, $io, $output): void {
            $this->synchronizeSchema();
            $io->success('Database schema is synchronized.');
        });

        return Command::SUCCESS;
    }

    private function waitForDatabase(int $waitSeconds): bool
    {
        $connection = $this->entityManager->getConnection();
        $deadline = microtime(true) + $waitSeconds;

        do {
            try {
                $connection->executeQuery('SELECT 1');

                return true;
            } catch (\Throwable) {
                usleep(500_000);
            }
        } while (microtime(true) < $deadline);

        return false;
    }

    private function synchronizeSchema(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        if ($metadata === []) {
            return;
        }

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->updateSchema($metadata, true);
    }

    private function runWithBootstrapLock(callable $callback): void
    {
        $connection = $this->entityManager->getConnection();
        if (!$connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $callback();

            return;
        }

        $params = [
            'namespace' => self::BOOTSTRAP_LOCK_NAMESPACE,
            'key' => self::BOOTSTRAP_LOCK_KEY,
        ];
        $types = [
            'namespace' => ParameterType::INTEGER,
            'key' => ParameterType::INTEGER,
        ];

        $connection->executeQuery(
            'SELECT pg_advisory_lock(:namespace, :key)',
            $params,
            $types,
        )->free();

        try {
            $callback();
        } finally {
            $connection->executeQuery(
                'SELECT pg_advisory_unlock(:namespace, :key)',
                $params,
                $types,
            )->free();
        }
    }
}
