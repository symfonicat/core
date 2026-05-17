<?php

namespace Symfonicat\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfonicat\Entity\Middleware;

/**
 * @extends ServiceEntityRepository<Middleware>
 */
final class MiddlewareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Middleware::class);
    }

    /**
     * @return Middleware[]
     */
    public function findAllOrderedById(): array
    {
        return $this->createQueryBuilder('middleware')
            ->orderBy('middleware.id', 'ASC')
            ->addOrderBy('middleware.class', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
