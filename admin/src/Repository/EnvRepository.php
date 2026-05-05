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
            ->leftJoin('env.envParent', 'envParent')
            ->addSelect('envParent')
            ->orderBy('envParent.id', 'ASC')
            ->addOrderBy('env.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Env[]
     */
    public function findAllForParent(?string $envParentId): array
    {
        $qb = $this->createQueryBuilder('env')
            ->leftJoin('env.envParent', 'envParent')
            ->addSelect('envParent');

        if ($envParentId !== null && trim($envParentId) !== '') {
            $qb
                ->andWhere('envParent.id = :envParentId')
                ->setParameter('envParentId', trim($envParentId));
        }

        return $qb
            ->orderBy('envParent.id', 'ASC')
            ->addOrderBy('env.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
