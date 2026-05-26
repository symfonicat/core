<?php

namespace Symfonicat\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

final class AdminYaml
{
    private const TABLE_PREFIX = 'symfonicat_';
    private const CONFIG_PATH = '/config/packages/symfonicat.yaml';

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $subdomainDir,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function dump(): array
    {
        $schemaManager = $this->connection->createSchemaManager();
        $tables = $this->orderedTables($schemaManager);
        $admin = [];
        $counts = [];

        foreach ($tables as $table) {
            $tableSchema = $schemaManager->introspectTable($table);
            $rows = $this->connection->fetchAllAssociative(sprintf(
                'SELECT * FROM %s%s',
                $this->connection->getDatabasePlatform()->quoteIdentifier($table),
                $this->orderByClause($tableSchema),
            ));

            $columns = $this->columnsByName($tableSchema->getColumns());
            $admin[$table] = array_map(
                fn (array $row): array => $this->normalizeDumpRow($row, $columns),
                $rows,
            );
            $counts[$table] = count($rows);
        }

        $configPath = $this->configPath();
        $config = $this->readConfig($configPath);
        $config['symfonicat'] = is_array($config['symfonicat'] ?? null) ? $config['symfonicat'] : [];
        $config['symfonicat']['vendors'] = $config['symfonicat']['vendors'] ?? ['symfonicat'];
        $config['symfonicat']['admin'] = $admin;

        $directory = dirname($configPath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create config directory "%s".', $directory));
        }

        file_put_contents($configPath, Yaml::dump(
            $config,
            8,
            4,
            Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE,
        ));

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    public function load(): array
    {
        $config = $this->readConfig($this->configPath());
        $symfonicat = $config['symfonicat'] ?? null;

        if (!is_array($symfonicat) || !array_key_exists('admin', $symfonicat)) {
            return [];
        }

        if (!is_array($symfonicat['admin'])) {
            throw new \RuntimeException('Expected "symfonicat.admin" to be a YAML map of table rows.');
        }

        $schemaManager = $this->connection->createSchemaManager();
        $tables = $this->orderedTables($schemaManager);
        $tableSet = array_fill_keys($tables, true);

        foreach (array_keys($symfonicat['admin']) as $table) {
            if (!is_string($table) || !isset($tableSet[$table])) {
                throw new \RuntimeException(sprintf('Unknown Symfonicat admin table "%s".', (string) $table));
            }
        }

        $schemas = [];
        foreach ($tables as $table) {
            $schemas[$table] = $schemaManager->introspectTable($table);
        }

        return $this->connection->transactional(function () use ($symfonicat, $tables, $schemas): array {
            $platform = $this->connection->getDatabasePlatform();
            $counts = [];

            if ($platform instanceof SQLitePlatform) {
                $this->connection->executeStatement('PRAGMA foreign_keys = OFF');
            }

            try {
                foreach (array_reverse($tables) as $table) {
                    $this->connection->executeStatement(sprintf('DELETE FROM %s', $platform->quoteIdentifier($table)));
                }

                foreach ($tables as $table) {
                    $rows = $symfonicat['admin'][$table] ?? [];
                    if (!is_array($rows)) {
                        throw new \RuntimeException(sprintf('Expected "symfonicat.admin.%s" to be a list of rows.', $table));
                    }

                    $counts[$table] = 0;
                    foreach ($rows as $row) {
                        if (!is_array($row)) {
                            throw new \RuntimeException(sprintf('Expected every "%s" row to be a YAML map.', $table));
                        }

                        $columns = $this->columnsByName($schemas[$table]->getColumns());
                        $this->connection->insert(
                            $table,
                            $this->normalizeLoadRow($table, $row, $columns),
                            $this->typesForLoadRow($table, $row, $columns),
                        );
                        ++$counts[$table];
                    }
                }

                $this->resetAutoincrementSequences($schemas);
            } finally {
                if ($platform instanceof SQLitePlatform) {
                    $this->connection->executeStatement('PRAGMA foreign_keys = ON');
                }
            }

            return $counts;
        });
    }

    private function configPath(): string
    {
        return rtrim($this->subdomainDir, '/').self::CONFIG_PATH;
    }

    /**
     * @return array<string, mixed>
     */
    private function readConfig(string $configPath): array
    {
        if (!is_file($configPath)) {
            return [];
        }

        $config = Yaml::parseFile($configPath);

        return is_array($config) ? $config : [];
    }

    /**
     * @return list<string>
     */
    private function orderedTables(AbstractSchemaManager $schemaManager): array
    {
        $tables = array_values(array_filter(
            $schemaManager->listTableNames(),
            static fn (string $table): bool => str_starts_with($table, self::TABLE_PREFIX) && $table !== self::TABLE_PREFIX.'admin',
        ));
        sort($tables);

        $tableSet = array_fill_keys($tables, true);
        $dependencies = array_fill_keys($tables, []);

        foreach ($tables as $table) {
            foreach ($schemaManager->listTableForeignKeys($table) as $foreignKey) {
                $foreignTable = $foreignKey->getForeignTableName();
                if (isset($tableSet[$foreignTable]) && $foreignTable !== $table) {
                    $dependencies[$table][] = $foreignTable;
                }
            }

            $dependencies[$table] = array_values(array_unique($dependencies[$table]));
        }

        $ordered = [];
        $visiting = [];
        $visited = [];

        $visit = function (string $table) use (&$visit, &$ordered, &$visiting, &$visited, $dependencies): void {
            if (isset($visited[$table])) {
                return;
            }

            if (isset($visiting[$table])) {
                throw new \RuntimeException(sprintf('Cycle detected while ordering Symfonicat table "%s".', $table));
            }

            $visiting[$table] = true;
            foreach ($dependencies[$table] as $dependency) {
                $visit($dependency);
            }
            unset($visiting[$table]);

            $visited[$table] = true;
            $ordered[] = $table;
        };

        foreach ($tables as $table) {
            $visit($table);
        }

        return $ordered;
    }

    private function orderByClause(Table $table): string
    {
        $primaryKey = $table->getPrimaryKey();
        if ($primaryKey === null) {
            return '';
        }

        $columns = array_map(
            fn (string $column): string => $this->connection->getDatabasePlatform()->quoteIdentifier($column),
            $primaryKey->getColumns(),
        );

        return $columns === [] ? '' : ' ORDER BY '.implode(', ', $columns);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, Column> $columns
     *
     * @return array<string, mixed>
     */
    private function normalizeDumpRow(array $row, array $columns): array
    {
        foreach ($row as $column => $value) {
            if (
                is_string($value)
                && (
                    (isset($columns[$column]) && $columns[$column]->getType() instanceof JsonType)
                    || str_starts_with(ltrim($value), '[')
                    || str_starts_with(ltrim($value), '{')
                )
            ) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row[$column] = $decoded;
                }
            }
        }

        return $row;
    }

    /**
     * @param array<string, Column> $columns
     *
     * @return array<string, Column>
     */
    private function columnsByName(array $columns): array
    {
        $byName = [];

        foreach ($columns as $column) {
            $byName[$column->getName()] = $column;
        }

        return $byName;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, Column> $columns
     *
     * @return array<string, mixed>
     */
    private function normalizeLoadRow(string $table, array $row, array $columns): array
    {
        $normalized = [];

        foreach ($row as $column => $value) {
            if ($table === 'symfonicat_subdomain' && $column === 'vendor') {
                continue;
            }

            if (!is_string($column) || !isset($columns[$column])) {
                throw new \RuntimeException(sprintf('Unknown column "%s" for Symfonicat admin table "%s".', (string) $column, $table));
            }

            if (($column === 'subdomain_id' || ($table === 'symfonicat_subdomain' && $column === 'id')) && is_string($value)) {
                $value = $this->normalizeSubdomainId($value);
            }

            if ($value !== null && (is_array($value) || is_object($value))) {
                $value = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $normalized[$column] = $value;
        }

        return $normalized;
    }

    private function normalizeSubdomainId(string $id): string
    {
        $id = trim($id, " \t\n\r\0\x0B/");

        return str_contains($id, '/') ? substr($id, strrpos($id, '/') + 1) : $id;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, Column> $columns
     *
     * @return array<string, string>
     */
    private function typesForLoadRow(string $table, array $row, array $columns): array
    {
        $types = [];

        foreach ($row as $column => $value) {
            if (!is_string($column) || !isset($columns[$column])) {
                continue;
            }

            if ($columns[$column]->getType() instanceof BooleanType) {
                $types[$column] = Types::BOOLEAN;
            }
        }

        return $types;
    }

    /**
     * @param array<string, Table> $schemas
     */
    private function resetAutoincrementSequences(array $schemas): void
    {
        $platform = $this->connection->getDatabasePlatform();

        foreach ($schemas as $table => $schema) {
            foreach ($schema->getColumns() as $column) {
                if (!$column->getAutoincrement()) {
                    continue;
                }

                $columnName = $column->getName();
                $quotedTable = $platform->quoteIdentifier($table);
                $quotedColumn = $platform->quoteIdentifier($columnName);
                $nextId = max(1, ((int) $this->connection->fetchOne(sprintf('SELECT MAX(%s) FROM %s', $quotedColumn, $quotedTable))) + 1);

                if ($platform instanceof PostgreSQLPlatform) {
                    $sequence = $this->connection->fetchOne(
                        'SELECT pg_get_serial_sequence(?, ?)',
                        [$table, $columnName],
                    );

                    if (is_string($sequence) && $sequence !== '') {
                        $this->connection->executeStatement(
                            'SELECT setval(?::regclass, ?, false)',
                            [$sequence, $nextId],
                        );
                    }

                    continue;
                }

                if ($platform instanceof AbstractMySQLPlatform) {
                    $this->connection->executeStatement(sprintf('ALTER TABLE %s AUTO_INCREMENT = %d', $quotedTable, $nextId));

                    continue;
                }

                if ($platform instanceof SQLitePlatform) {
                    $this->connection->executeStatement(
                        'UPDATE sqlite_sequence SET seq = ? WHERE name = ?',
                        [$nextId - 1, $table],
                    );
                }
            }
        }
    }
}
