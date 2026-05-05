<?php

namespace Symfonicat\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfonicat\Entity\EnvParent;

/**
 * @extends ServiceEntityRepository<EnvParent>
 */
final class EnvParentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EnvParent::class);
    }

    /**
     * @return EnvParent[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('envParent')
            ->orderBy('envParent.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
