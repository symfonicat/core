<?php

namespace Symfonicat\Repository;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Project;
use Symfonicat\Entity\RoutingRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RoutingRule>
 */
class RoutingRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoutingRule::class);
    }

    public function findOneRedirectRuleForDomain(Domain $domain): ?RoutingRule
    {
        return $this->createQueryBuilder('rule')
            ->andWhere('rule.type = :type')
            ->andWhere('rule.redirectType = :redirectType')
            ->andWhere('IDENTITY(rule.domain) = :domainId')
            ->setParameter('type', RoutingRule::TYPE_REDIRECT)
            ->setParameter('redirectType', RoutingRule::REDIRECT_TYPE_DOMAIN)
            ->setParameter('domainId', $domain->getId())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return RoutingRule[]
     */
    public function findTypeDomainByDomain(Domain $domain): array
    {
        return $this->createQueryBuilder('rule')
            ->andWhere('rule.type = :type')
            ->andWhere('IDENTITY(rule.domain) = :domainId')
            ->setParameter('type', RoutingRule::TYPE_DOMAIN)
            ->setParameter('domainId', $domain->getId())
            ->getQuery()
            ->getResult();
    }

    public function findOneRedirectRuleForProject(Project $project): ?RoutingRule
    {
        return $this->createQueryBuilder('rule')
            ->andWhere('rule.type = :type')
            ->andWhere('rule.redirectType = :redirectType')
            ->andWhere('IDENTITY(rule.project) = :projectId')
            ->setParameter('type', RoutingRule::TYPE_REDIRECT)
            ->setParameter('redirectType', RoutingRule::REDIRECT_TYPE_PROJECT)
            ->setParameter('projectId', $project->getId())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return RoutingRule[]
     */
    public function findTypeProjectByProject(Project $project): array
    {
        return $this->createQueryBuilder('rule')
            ->andWhere('rule.type = :type')
            ->andWhere('IDENTITY(rule.project) = :projectId')
            ->setParameter('type', RoutingRule::TYPE_PROJECT)
            ->setParameter('projectId', $project->getId())
            ->getQuery()
            ->getResult();
    }

    public function findOneRouteRuleForDomain(Domain $domain): ?RoutingRule
    {
        return $this->createQueryBuilder('rule')
            ->andWhere('rule.type = :type')
            ->andWhere('rule.routeType = :routeType')
            ->andWhere('IDENTITY(rule.domain) = :domainId')
            ->setParameter('type', RoutingRule::TYPE_ROUTE)
            ->setParameter('routeType', RoutingRule::ROUTE_TYPE_DOMAIN)
            ->setParameter('domainId', $domain->getId())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneRouteRuleForProject(Project $project): ?RoutingRule
    {
        return $this->createQueryBuilder('rule')
            ->andWhere('rule.type = :type')
            ->andWhere('rule.routeType = :routeType')
            ->andWhere('IDENTITY(rule.project) = :projectId')
            ->setParameter('type', RoutingRule::TYPE_ROUTE)
            ->setParameter('routeType', RoutingRule::ROUTE_TYPE_PROJECT)
            ->setParameter('projectId', $project->getId())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return RoutingRule[]
     */
    public function findTypeApplication(): array
    {
        return $this->createQueryBuilder('rule')
            ->andWhere('rule.type = :type')
            ->andWhere('rule.application IS NOT NULL')
            ->setParameter('type', RoutingRule::TYPE_APPLICATION)
            ->getQuery()
            ->getResult();
    }
}
