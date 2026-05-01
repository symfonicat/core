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
        $qb = $this->createQueryBuilder('p')
            ->innerJoin('p.domains', 'd')
            ->andWhere('d.id = :domainId')
            ->setParameter('domainId', $domainId)
            ->setMaxResults(1);

        // Match either exact id or package-prefixed id that ends with "/{id}"
        $qb->andWhere($qb->expr()->orX('p.id = :id', 'p.id LIKE :idSuffix'))
            ->setParameter('id', $id)
            ->setParameter('idSuffix', '%/'.$id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @return Project[]
     */
    public function findAllOrderedById(): array
    {
        return $this->createQueryBuilder('project')
            ->orderBy('project.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
