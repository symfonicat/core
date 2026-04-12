<?php

namespace Symfonicat\Repository;

use Symfonicat\Entity\RoutingRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RoutingRule>
 */
final class RoutingRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoutingRule::class);
    }

    /**
     * @return RoutingRule[]
     */
    public function findByDomain(string $domainId): array
    {
        return $this->createQueryBuilder('rule')
            ->andWhere('IDENTITY(rule.domain) = :domainId')
            ->setParameter('domainId', $domainId)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return RoutingRule[]
     */
    public function findAllDomainRules(): array
    {
        return $this->createQueryBuilder('rule')
            ->andWhere('rule.domain IS NOT NULL')
            ->getQuery()
            ->getResult();
    }
}
