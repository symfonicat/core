<?php

namespace Symfonicat\Repository;

use Symfonicat\Entity\Subdomain;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subdomain>
 */
class SubdomainRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subdomain::class);
    }

    //    /**
    //     * @return Subdomain[] Returns an array of Subdomain objects
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

    public function findOneByIdForDomain(string $id, string $domainId): ?Subdomain
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $qb = $this->createQueryBuilder('subdomain')
            ->innerJoin('subdomain.domains', 'd')
            ->andWhere('d.id = :domainId')
            ->andWhere('subdomain.id = :id')
            ->setParameter('domainId', $domainId)
            ->setParameter('id', $id)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findOneById(string $id): ?Subdomain
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        return $this->createQueryBuilder('subdomain')
            ->andWhere('subdomain.id = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Subdomain[]
     */
    public function findAllOrderedById(): array
    {
        return $this->createQueryBuilder('subdomain')
            ->orderBy('subdomain.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
