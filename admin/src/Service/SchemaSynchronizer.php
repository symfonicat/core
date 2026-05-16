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

        foreach ($this->middlewareRepository->findAllOrderedByClass() as $middleware) {
            $existing[$middleware->getClass()] = $middleware;
        }

        $configuredClasses = $this->middlewareClassProvider->classes();
        $configuredClassSet = array_fill_keys($configuredClasses, true);

        foreach ($configuredClasses as $class) {
            if (isset($existing[$class])) {
                continue;
            }

            $this->entityManager->persist((new Middleware())->setClass($class));
        }

        foreach ($existing as $class => $middleware) {
            if (isset($configuredClassSet[$class])) {
                continue;
            }

            $this->entityManager->remove($middleware);
        }

        $this->entityManager->flush();
    }
}
