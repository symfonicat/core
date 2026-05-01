<?php

namespace Symfonicat\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfonicat\Entity\Application;

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
    public function findAllOrderedById(): array
    {
        return $this->createQueryBuilder('application')
            ->orderBy('application.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByFullOrCleanId(string $id): ?Application
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        return $this->createQueryBuilder('application')
            ->andWhere('application.id = :id OR application.id LIKE :idSuffix')
            ->setParameter('id', $id)
            ->setParameter('idSuffix', '%/'.$id)
            ->orderBy('CASE WHEN application.id = :id THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('application.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
