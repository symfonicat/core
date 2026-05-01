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
            ->setParameter('domainId', $domain->getId(true))
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
            ->setParameter('domainId', $domain->getId(true))
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
            ->setParameter('projectId', $project->getId(true))
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
            ->setParameter('projectId', $project->getId(true))
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
            ->setParameter('domainId', $domain->getId(true))
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
            ->setParameter('projectId', $project->getId(true))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneTypeApplicationByApplicationId(string $applicationId): ?RoutingRule
    {
        $qb = $this->createQueryBuilder('rule')
            ->andWhere('rule.type = :type')
            ->setParameter('type', RoutingRule::TYPE_APPLICATION)
            ->setMaxResults(1)
            ->orderBy('rule.id', 'ASC');

        $qb->andWhere($qb->expr()->orX('IDENTITY(rule.application) = :applicationId', 'IDENTITY(rule.application) LIKE :applicationIdSuffix'))
            ->setParameter('applicationId', $applicationId)
            ->setParameter('applicationIdSuffix', '%/'.$applicationId);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findOneTypeApplicationArgumentsByApplicationId(string $applicationId): ?RoutingRule
    {
        $qb = $this->createQueryBuilder('rule')
            ->andWhere('rule.type = :type')
            ->setParameter('type', RoutingRule::TYPE_APPLICATION)
            ->setMaxResults(1)
            ->orderBy('rule.id', 'ASC');

        $qb->andWhere($qb->expr()->orX('IDENTITY(rule.application) = :applicationId', 'IDENTITY(rule.application) LIKE :applicationIdSuffix'))
            ->setParameter('applicationId', $applicationId)
            ->setParameter('applicationIdSuffix', '%/'.$applicationId);

        $qb->andWhere($qb->expr()->orX(
            'rule.applicationType = :applicationType',
            'rule.applicationType IS NULL',
        ))->setParameter('applicationType', RoutingRule::APPLICATION_TYPE_ARGUMENTS);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findOneTypeApplicationRouteByApplicationId(string $applicationId): ?RoutingRule
    {
        $qb = $this->createQueryBuilder('rule')
            ->andWhere('rule.type = :type')
            ->andWhere('rule.applicationType = :applicationType')
            ->setParameter('type', RoutingRule::TYPE_APPLICATION)
            ->setParameter('applicationType', RoutingRule::APPLICATION_TYPE_ROUTE)
            ->orderBy('rule.id', 'ASC')
            ->setMaxResults(1);

        $qb->andWhere($qb->expr()->orX('IDENTITY(rule.application) = :applicationId', 'IDENTITY(rule.application) LIKE :applicationIdSuffix'))
            ->setParameter('applicationId', $applicationId)
            ->setParameter('applicationIdSuffix', '%/'.$applicationId);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findOneTypeApplicationByRoute(string $route): ?RoutingRule
    {
        return $this->createQueryBuilder('rule')
            ->andWhere('rule.type = :type')
            ->andWhere('rule.applicationType = :applicationType')
            ->andWhere('rule.route = :route')
            ->setParameter('type', RoutingRule::TYPE_APPLICATION)
            ->setParameter('applicationType', RoutingRule::APPLICATION_TYPE_ROUTE)
            ->setParameter('route', $route)
            ->orderBy('rule.id', 'ASC')
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

    /**
     * @return RoutingRule[]
     */
    public function findTypeApplicationByApplicationType(string $applicationType): array
    {
        $qb = $this->createQueryBuilder('rule')
            ->andWhere('rule.type = :type')
            ->andWhere('rule.application IS NOT NULL')
            ->setParameter('type', RoutingRule::TYPE_APPLICATION);

        if ($applicationType === RoutingRule::APPLICATION_TYPE_ARGUMENTS) {
            $qb->andWhere($qb->expr()->orX(
                'rule.applicationType = :applicationType',
                'rule.applicationType IS NULL',
            ));
        } else {
            $qb->andWhere('rule.applicationType = :applicationType');
        }

        return $qb
            ->setParameter('applicationType', $applicationType)
            ->getQuery()
            ->getResult();
    }
}
