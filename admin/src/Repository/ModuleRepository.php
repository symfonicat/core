<?php

namespace Symfonicat\Repository;

use Symfonicat\Entity\Module;
use Symfonicat\Entity\Subdomain;
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

    public function findOneByFullOrCleanId(string $id): ?Module
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        return $this->createQueryBuilder('m')
            ->andWhere('m.id = :id OR m.id LIKE :idSuffix')
            ->setParameter('id', $id)
            ->setParameter('idSuffix', '%/'.$id)
            ->orderBy('CASE WHEN m.id = :id THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Module[]
     */
    public function findForSubdomain(Subdomain $subdomain): array
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.subdomains', 'p')
            ->andWhere('p = :subdomain')
            ->setParameter('subdomain', $subdomain)
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
