<?php

namespace Symfonicat\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Electron;
use Symfonicat\Entity\Subdomain;

/**
 * @extends ServiceEntityRepository<Electron>
 */
final class ElectronRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Electron::class);
    }

    /**
     * @return Electron[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('electron')
            ->leftJoin('electron.domain', 'domain')
            ->leftJoin('electron.subdomain', 'subdomain')
            ->addSelect('domain', 'subdomain')
            ->orderBy('electron.type', 'ASC')
            ->addOrderBy('electron.name', 'ASC')
            ->addOrderBy('electron.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForDomain(Domain $domain): ?Electron
    {
        return $this->findOneBy([
            'type' => Electron::TYPE_DOMAIN,
            'domain' => $domain,
        ]);
    }

    public function findOneForSubdomain(Subdomain $subdomain): ?Electron
    {
        return $this->findOneBy([
            'type' => Electron::TYPE_PROJECT,
            'subdomain' => $subdomain,
        ]);
    }

    public function findOneForSubdomainAndDomain(Subdomain $subdomain, Domain $domain): ?Electron
    {
        return $this->findOneBy([
            'type' => Electron::TYPE_PROJECT,
            'subdomain' => $subdomain,
            'domain' => $domain,
        ]);
    }
}
