<?php

namespace Symfonicat\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'symfonicat:purge',
    description: 'Drop every symfonicat_* database table, including admin tables.',
)]
final class PurgeCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $schemaManager = $this->connection->createSchemaManager();
        $platform = $this->connection->getDatabasePlatform();
        $tables = array_values(array_filter(
            $schemaManager->listTableNames(),
            static fn (string $table): bool => str_starts_with($table, 'symfonicat_'),
        ));
        rsort($tables, SORT_STRING);

        if ($tables === []) {
            $io->success('No symfonicat_* tables found.');

            return Command::SUCCESS;
        }

        if ($platform instanceof SQLitePlatform) {
            $this->connection->executeStatement('PRAGMA foreign_keys = OFF');
        } elseif ($platform instanceof AbstractMySQLPlatform) {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            foreach ($tables as $table) {
                $quotedTable = $platform->quoteIdentifier($table);
                $cascade = $platform instanceof PostgreSQLPlatform ? ' CASCADE' : '';
                $this->connection->executeStatement(sprintf('DROP TABLE %s%s', $quotedTable, $cascade));
            }
        } finally {
            if ($platform instanceof SQLitePlatform) {
                $this->connection->executeStatement('PRAGMA foreign_keys = ON');
            } elseif ($platform instanceof AbstractMySQLPlatform) {
                $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        $io->success(sprintf('Dropped %d symfonicat_* tables.', count($tables)));

        return Command::SUCCESS;
    }
}
