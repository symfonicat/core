<?php

namespace Symfonicat\Repository;

use Symfonicat\Entity\Env;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Env>
 */
final class EnvRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Env::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('env')
            ->orderBy('env.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
