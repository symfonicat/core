<?php

namespace Symfonicat\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfonicat\Entity\Parcel;

/**
 * @extends ServiceEntityRepository<Parcel>
 */
final class ParcelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Parcel::class);
    }

    /**
     * @return Parcel[]
     */
    public function findAllOrderedById(): array
    {
        return $this->createQueryBuilder('parcel')
            ->orderBy('parcel.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
