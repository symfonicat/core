<?php

namespace Symfonicat\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

final class SchemaSynchronizer
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function synchronize(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        if ($metadata === []) {
            return;
        }

        (new SchemaTool($this->entityManager))->updateSchema($metadata);
    }
}
