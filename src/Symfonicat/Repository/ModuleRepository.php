<?php

namespace Symfonicat\Repository;

use Symfonicat\Entity\Module;
use Symfonicat\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Module>
 */
class ModuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Module::class);
    }

    /**
     * @return Module[]
     */
    public function findAllOrderedById(): array
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Module[]
     */
    public function findForProject(Project $project): array
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.projects', 'p')
            ->andWhere('p = :project')
            ->setParameter('project', $project)
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
