<?php

namespace Symfonicat\Repository;

use Symfonicat\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    //    /**
    //     * @return Project[] Returns an array of Project objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    public function findOneByIdForDomain(string $id, string $domainId): ?Project
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.domains', 'd')
            ->andWhere('p.id = :id')
            ->andWhere('d.id = :domainId')
            ->setParameter('id', $id)
            ->setParameter('domainId', $domainId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
