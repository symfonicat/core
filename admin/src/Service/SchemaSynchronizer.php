<?php

namespace Symfonicat\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfonicat\Entity\Middleware;
use Symfonicat\Repository\MiddlewareRepository;

final class SchemaSynchronizer
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MiddlewareClassProvider $middlewareClassProvider,
        private readonly MiddlewareRepository $middlewareRepository,
    ) {
    }

    public function synchronize(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        if ($metadata === []) {
            return;
        }

        (new SchemaTool($this->entityManager))->updateSchema($metadata);
        $this->synchronizeMiddlewares();
    }

    private function synchronizeMiddlewares(): void
    {
        $existing = [];

        foreach ($this->middlewareRepository->findAllOrderedById() as $middleware) {
            $existing[$middleware->getClass()] = $middleware;
        }

        $configuredDefinitions = $this->middlewareClassProvider->definitions();
        $configuredClassSet = array_fill_keys(array_column($configuredDefinitions, 'class'), true);

        foreach ($configuredDefinitions as $definition) {
            $class = $definition['class'];
            $middleware = $existing[$class] ?? null;
            if ($middleware instanceof Middleware) {
                if ($middleware->getId() !== $definition['id']) {
                    $this->rekeyMiddleware((string) $middleware->getId(), $definition['id']);
                }

                continue;
            }

            $this->entityManager->persist((new Middleware())
                ->setId($definition['id'])
                ->setClass($class));
        }

        foreach ($existing as $class => $middleware) {
            if (isset($configuredClassSet[$class])) {
                continue;
            }

            $this->entityManager->remove($middleware);
        }

        $this->entityManager->flush();
    }

    private function rekeyMiddleware(string $oldId, string $newId): void
    {
        $oldId = trim($oldId);
        $newId = trim($newId);
        if ($oldId === '' || $newId === '' || $oldId === $newId) {
            return;
        }

        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        foreach ([
            'symfonicat_domain_middleware',
            'symfonicat_subdomain_middleware',
            'symfonicat_endpoint_middleware',
        ] as $table) {
            $connection->executeStatement(sprintf(
                'UPDATE %s SET middleware_id = :new_id WHERE middleware_id = :old_id',
                $platform->quoteIdentifier($table),
            ), [
                'new_id' => $newId,
                'old_id' => $oldId,
            ]);
        }

        $connection->executeStatement(sprintf(
            'UPDATE %s SET id = :new_id WHERE id = :old_id',
            $platform->quoteIdentifier('symfonicat_middleware'),
        ), [
            'new_id' => $newId,
            'old_id' => $oldId,
        ]);
    }
}
