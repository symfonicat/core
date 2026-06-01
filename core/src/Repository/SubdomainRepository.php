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

    public function findOneByAffixForDomain(string $affix, string $domainId): ?Subdomain
    {
        $affix = trim($affix);
        if ($affix === '') {
            return null;
        }

        $qb = $this->createQueryBuilder('subdomain')
            ->innerJoin('subdomain.domain', 'd')
            ->andWhere('d.id = :domainId')
            ->andWhere('subdomain.affix = :affix')
            ->setParameter('domainId', $domainId)
            ->setParameter('affix', $affix)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findOneByAffix(string $affix): ?Subdomain
    {
        $affix = trim($affix);
        if ($affix === '') {
            return null;
        }

        return $this->createQueryBuilder('subdomain')
            ->andWhere('subdomain.affix = :affix')
            ->andWhere('subdomain.domain IS NULL')
            ->setParameter('affix', $affix)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Subdomain[]
     */
    public function findAllOrderedByAffix(): array
    {
        return $this->createQueryBuilder('subdomain')
            ->leftJoin('subdomain.domain', 'domain')
            ->addOrderBy('domain.id', 'ASC')
            ->addOrderBy('subdomain.affix', 'ASC')
            ->addOrderBy('subdomain.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdForDomain(string $id, string $domainId): ?Subdomain
    {
        return $this->findOneByAffixForDomain($id, $domainId);
    }

    public function findOneById(string $id): ?Subdomain
    {
        return $this->findOneByAffix($id);
    }

    /**
     * @return Subdomain[]
     */
    public function findAllOrderedById(): array
    {
        return $this->findAllOrderedByAffix();
    }
}
