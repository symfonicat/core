<?php

namespace Symfonicat\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Electron;
use Symfonicat\Entity\Project;

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
            ->leftJoin('electron.project', 'project')
            ->leftJoin('electron.application', 'application')
            ->addSelect('domain', 'project', 'application')
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

    public function findOneForProject(Project $project): ?Electron
    {
        return $this->findOneBy([
            'type' => Electron::TYPE_PROJECT,
            'project' => $project,
        ]);
    }

    public function findOneForProjectAndDomain(Project $project, Domain $domain): ?Electron
    {
        return $this->findOneBy([
            'type' => Electron::TYPE_PROJECT,
            'project' => $project,
            'domain' => $domain,
        ]);
    }

    public function findOneForApplication(Application $application): ?Electron
    {
        return $this->findOneBy([
            'type' => Electron::TYPE_APPLICATION,
            'application' => $application,
        ]);
    }
}
