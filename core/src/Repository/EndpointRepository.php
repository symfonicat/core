<?php

namespace Symfonicat\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfonicat\Entity\Endpoint;

/**
 * @extends ServiceEntityRepository<Endpoint>
 */
final class EndpointRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Endpoint::class);
    }

    /**
     * @return Endpoint[]
     */
    public function findAllOrderedById(): array
    {
        return $this->createQueryBuilder('endpoint')
            ->orderBy('endpoint.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
