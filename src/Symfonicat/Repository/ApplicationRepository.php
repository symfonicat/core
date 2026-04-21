<?php

namespace Symfonicat\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfonicat\Entity\Application;

/**
 * @extends ServiceEntityRepository<Application>
 */
final class ApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Application::class);
    }

    /**
     * @return Application[]
     */
    public function findAllOrderedById(): array
    {
        return $this->createQueryBuilder('application')
            ->orderBy('application.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
