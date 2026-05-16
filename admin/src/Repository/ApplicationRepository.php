<?php

namespace Symfonicat\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\Subdomain;

/**
 * @extends ServiceEntityRepository<Application>
 */
final class ApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Application::class);
    }

    /**
     * @return Application[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('application')
            ->leftJoin('application.domain', 'domain')
            ->leftJoin('application.subdomain', 'subdomain')
            ->leftJoin('application.bundle', 'bundle')
            ->addSelect('domain', 'subdomain', 'bundle')
            ->orderBy('application.type', 'ASC')
            ->addOrderBy('application.name', 'ASC')
            ->addOrderBy('application.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForDomain(Domain $domain): ?Application
    {
        return $this->findOneBy([
            'type' => Application::TYPE_DOMAIN,
            'domain' => $domain,
        ]);
    }

    public function findOneForSubdomain(Subdomain $subdomain): ?Application
    {
        return $this->findOneBy([
            'type' => Application::TYPE_SUBDOMAIN,
            'subdomain' => $subdomain,
        ]);
    }

    public function findOneForSubdomainAndDomain(Subdomain $subdomain, Domain $domain): ?Application
    {
        return $this->findOneBy([
            'type' => Application::TYPE_SUBDOMAIN,
            'subdomain' => $subdomain,
            'domain' => $domain,
        ]);
    }
}
