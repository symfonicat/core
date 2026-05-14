<?php

namespace Symfonicat\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfonicat\Repository\ModuleRepository;
use Symfonicat\Entity\Module;
use Symfony\Component\HttpFoundation\RequestStack;

final class ModuleService
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly PathService $pathService,
        private readonly ModuleRepository $moduleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PackageDiscoveryService $packageDiscoveryService,
    ) {
    }

    public function load(): mixed
    {
        $args = $this->pathService->args();
        $arg0 = $args[0] ?? null;
        $moduleId = implode('/', array_slice($args, 1));

        if ($arg0 !== 'm' || $moduleId === '') {
            return NULL;
        }

        return $this->moduleRepository->findOneByFullOrCleanId($moduleId);
    }

    /**
     * @param (callable(Module, list<array{
     *     association: string,
     *     count: int,
     *     delete_action: string,
     *     entity: class-string,
     *     module_columns: list<string>,
     *     table: string,
     *     type: string
     * }>): bool)|null $confirmModuleDeletion
     *
     * @return array{
     *     created: list<array{id: string, package: string}>,
     *     deleted: list<array{
     *         id: string,
     *         package: string,
     *         references: list<array{
     *             association: string,
     *             count: int,
     *             delete_action: string,
     *             entity: class-string,
     *             module_columns: list<string>,
     *             table: string,
     *             type: string
     *         }>
     *     }>,
     *     updated: list<array{id: string, field: string, from: string, to: string}>
     * }
     */
    public function sync(?callable $confirmModuleDeletion = null): array
    {
        $packageModules = $this->discoverPackageModules();
        $databaseModules = $this->indexDatabaseModules();

        $created = $this->createMissingModules($packageModules, $databaseModules);
        $updated = $this->updateExistingModules($packageModules, $databaseModules);
        $deleted = $this->deleteMissingPackageModules($packageModules, $databaseModules, $confirmModuleDeletion);

        $this->entityManager->flush();

        return [
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
        ];
    }

    /**
     * @return array<string, array{package: string}>
     */
    private function discoverPackageModules(): array
    {
        $modules = [];

        foreach ($this->packageDiscoveryService->discoverModules() as $moduleId => $module) {
            $modules[$moduleId] = [
                'package' => $module['package'],
            ];
        }

        return $modules;
    }

    /**
     * @return array<string, Module>
     */
    private function indexDatabaseModules(): array
    {
        $modules = [];

        foreach ($this->moduleRepository->findAllOrderedById() as $module) {
            $moduleId = $module->getId();
            if ($moduleId === null) {
                continue;
            }

            $modules[$moduleId] = $module;
        }

        return $modules;
    }

    /**
     * @param array<string, array{package: string}> $packageModules
     * @param array<string, Module> $databaseModules
     *
     * @return list<array{id: string, package: string}>
     */
    private function createMissingModules(array $packageModules, array &$databaseModules): array
    {
        $created = [];

        foreach ($packageModules as $moduleId => $moduleData) {
            if (isset($databaseModules[$moduleId])) {
                continue;
            }

            $module = (new Module())
                ->setId($moduleId)
                ->setPackage($moduleData['package']);

            $this->entityManager->persist($module);
            $databaseModules[$moduleId] = $module;
            $created[] = [
                'id' => $moduleId,
                'package' => $moduleData['package'],
            ];
        }

        return $created;
    }

    /**
     * @param array<string, array{package: string}> $packageModules
     * @param array<string, Module> $databaseModules
     *
     * @return list<array{id: string, field: string, from: string, to: string}>
     */
    private function updateExistingModules(array $packageModules, array $databaseModules): array
    {
        $updated = [];

        foreach ($packageModules as $moduleId => $moduleData) {
            $module = $databaseModules[$moduleId] ?? null;
            if (!$module instanceof Module) {
                continue;
            }

            $currentPackage = $module->getPackage() ?? '';
            if ($currentPackage !== $moduleData['package']) {
                $module->setPackage($moduleData['package']);
                $updated[] = [
                    'id' => $moduleId,
                    'field' => 'package',
                    'from' => $currentPackage,
                    'to' => $moduleData['package'],
                ];
            }
        }

        return $updated;
    }

    /**
     * @param array<string, array{package: string}> $packageModules
     * @param array<string, Module> $databaseModules
     * @param (callable(Module, list<array{
     *     association: string,
     *     count: int,
     *     delete_action: string,
     *     entity: class-string,
     *     module_columns: list<string>,
     *     table: string,
     *     type: string
     * }>): bool)|null $confirmModuleDeletion
     *
     * @return list<array{
     *     id: string,
     *     package: string,
     *     references: list<array{
     *         association: string,
     *         count: int,
     *         delete_action: string,
     *         entity: class-string,
     *         module_columns: list<string>,
     *         table: string,
     *         type: string
     *     }>
     * }>
     */
    private function deleteMissingPackageModules(array $packageModules, array $databaseModules, ?callable $confirmModuleDeletion): array
    {
        $deleted = [];

        foreach ($databaseModules as $moduleId => $module) {
            if (isset($packageModules[$moduleId])) {
                continue;
            }

            $references = $this->findModuleReferences($module);
            if ($references !== []) {
                if ($confirmModuleDeletion === null) {
                    throw new \RuntimeException(sprintf(
                        'Module "%s" still has referencing entity rows in %s.',
                        $moduleId,
                        implode(', ', array_map(static fn (array $reference): string => $reference['table'], $references)),
                    ));
                }

                if (!(bool) $confirmModuleDeletion($module, $references)) {
                    throw new \RuntimeException(sprintf('Aborted removing module "%s".', $moduleId));
                }

                $this->deleteModuleReferences($module, $references);
            }

            $this->entityManager->remove($module);
            $deleted[] = [
                'id' => $moduleId,
                'package' => $module->getPackage() ?? $moduleId,
                'references' => $references,
            ];
        }

        return $deleted;
    }

    /**
     * @return list<array{
     *     association: string,
     *     count: int,
     *     delete_action: string,
     *     entity: class-string,
     *     module_columns: list<string>,
     *     table: string,
     *     type: string
     * }>
     */
    private function findModuleReferences(Module $module): array
    {
        $moduleId = $module->getId();
        if ($moduleId === null) {
            throw new \RuntimeException('Cannot synchronize a module without an id.');
        }

        $references = [];

        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            foreach ($metadata->getAssociationMappings() as $association) {
                if ($association->targetEntity !== Module::class) {
                    continue;
                }

                $reference = $this->buildModuleReference($metadata, $association);
                if ($reference === null) {
                    continue;
                }

                $count = $this->countReferenceRows($reference['table'], $reference['module_columns'], $moduleId);
                if ($count < 1) {
                    continue;
                }

                $reference['count'] = $count;
                $references[] = $reference;
            }
        }

        usort(
            $references,
            static fn (array $left, array $right): int => [$left['table'], $left['entity'], $left['association']] <=> [$right['table'], $right['entity'], $right['association']],
        );

        return $references;
    }

    /**
     * @return array{
     *     association: string,
     *     delete_action: string,
     *     entity: class-string,
     *     module_columns: list<string>,
     *     table: string,
     *     type: string
     * }|null
     */
    private function buildModuleReference(ClassMetadata $metadata, AssociationMapping $association): ?array
    {
        if ($association->isManyToMany()) {
            return $this->buildManyToManyReference($metadata, $association);
        }

        if ($association->isToOneOwningSide()) {
            return $this->buildToOneReference($metadata, $association);
        }

        return null;
    }

    /**
     * @return array{
     *     association: string,
     *     delete_action: string,
     *     entity: class-string,
     *     module_columns: list<string>,
     *     table: string,
     *     type: string
     * }|null
     */
    private function buildManyToManyReference(ClassMetadata $metadata, AssociationMapping $association): ?array
    {
        $owningAssociation = $association;

        if (!$owningAssociation->isManyToManyOwningSide()) {
            $mappedBy = $association->mappedBy ?? null;
            if ($mappedBy === null) {
                return null;
            }

            $targetMetadata = $this->entityManager->getClassMetadata($association->targetEntity);
            $owningAssociation = $targetMetadata->getAssociationMapping($mappedBy);

            if (!$owningAssociation->isManyToManyOwningSide()) {
                return null;
            }
        }

        $joinTable = $owningAssociation->joinTable;
        $moduleColumns = $owningAssociation->sourceEntity === Module::class
            ? $joinTable->joinColumns
            : $joinTable->inverseJoinColumns;

        return [
            'type' => 'many_to_many',
            'table' => $joinTable->name,
            'entity' => $metadata->getName(),
            'association' => $association->fieldName,
            'delete_action' => 'Delete association rows',
            'module_columns' => $this->extractModuleColumnNames(
                $moduleColumns,
                sprintf('%s::%s', $metadata->getName(), $association->fieldName),
            ),
        ];
    }

    /**
     * @return array{
     *     association: string,
     *     delete_action: string,
     *     entity: class-string,
     *     module_columns: list<string>,
     *     table: string,
     *     type: string
     * }|null
     */
    private function buildToOneReference(ClassMetadata $metadata, AssociationMapping $association): ?array
    {
        if ($association->sourceEntity === Module::class) {
            return null;
        }

        return [
            'type' => 'entity_rows',
            'table' => $metadata->getTableName(),
            'entity' => $metadata->getName(),
            'association' => $association->fieldName,
            'delete_action' => 'Delete referencing entity rows',
            'module_columns' => $this->extractModuleColumnNames(
                $association->joinColumns,
                sprintf('%s::%s', $metadata->getName(), $association->fieldName),
            ),
        ];
    }

    /**
     * @param list<object{name: string}> $moduleColumns
     *
     * @return list<string>
     */
    private function extractModuleColumnNames(array $moduleColumns, string $context): array
    {
        $columnNames = array_values(array_unique(array_map(
            static fn (object $moduleColumn): string => $moduleColumn->name,
            $moduleColumns,
        )));

        if (count($columnNames) !== 1) {
            throw new \RuntimeException(sprintf(
                'Only single-column module references are supported for %s; found %d columns.',
                $context,
                count($columnNames),
            ));
        }

        return $columnNames;
    }

    /**
     * @param list<string> $moduleColumns
     */
    private function countReferenceRows(string $table, array $moduleColumns, string $moduleId): int
    {
        [$whereClause, $parameters] = $this->buildModuleIdentifierWhereClause($moduleColumns, $moduleId);

        return (int) $this->entityManager->getConnection()->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE %s', $this->quoteIdentifier($table), $whereClause),
            $parameters,
        );
    }

    /**
     * @param list<array{
     *     association: string,
     *     count: int,
     *     delete_action: string,
     *     entity: class-string,
     *     module_columns: list<string>,
     *     table: string,
     *     type: string
     * }> $references
     */
    private function deleteModuleReferences(Module $module, array $references): void
    {
        $moduleId = $module->getId();
        if ($moduleId === null) {
            throw new \RuntimeException('Cannot delete references for a module without an id.');
        }

        foreach ($references as $reference) {
            [$whereClause, $parameters] = $this->buildModuleIdentifierWhereClause($reference['module_columns'], $moduleId);

            $this->entityManager->getConnection()->executeStatement(
                sprintf('DELETE FROM %s WHERE %s', $this->quoteIdentifier($reference['table']), $whereClause),
                $parameters,
            );
        }
    }

    /**
     * @param list<string> $moduleColumns
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function buildModuleIdentifierWhereClause(array $moduleColumns, string $moduleId): array
    {
        $clauses = [];
        $parameters = [];

        foreach (array_values($moduleColumns) as $index => $moduleColumn) {
            $parameter = sprintf('moduleId%d', $index);
            $clauses[] = sprintf('%s = :%s', $this->quoteIdentifier($moduleColumn), $parameter);
            $parameters[$parameter] = $moduleId;
        }

        return [implode(' AND ', $clauses), $parameters];
    }

    private function quoteIdentifier(string $identifier): string
    {
        return $this->entityManager->getConnection()->getDatabasePlatform()->quoteIdentifier($identifier);
    }
}
