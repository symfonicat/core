<?php

namespace Symfonicat\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfonicat\Entity\Bundle;

/**
 * @extends ServiceEntityRepository<Bundle>
 */
final class BundleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bundle::class);
    }

    /**
     * @return Bundle[]
     */
    public function findAllOrderedById(): array
    {
        return $this->createQueryBuilder('bundle')
            ->orderBy('bundle.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
