<?php

namespace Symfonicat\Repository;

use Symfonicat\Entity\Domain;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Domain>
 */
class DomainRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Domain::class);
    }

    /**
     * @return Domain[]
     */
    public function findAllOrderedById(): array
    {
        return $this->createQueryBuilder('domain')
            ->orderBy('domain.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByHost(string $host): ?Domain
    {
        $host = trim($host);
        if ($host === '') {
            return null;
        }

        $qb = $this->createQueryBuilder('domain')
            ->andWhere('domain.id = :host')
            ->setParameter('host', $host)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
