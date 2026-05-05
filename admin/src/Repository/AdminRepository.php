<?php

namespace Symfonicat\Repository;

use Symfonicat\Entity\Admin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Admin>
 */
final class AdminRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Admin::class);
    }

    public function findOneByEmail(string $email): ?Admin
    {
        return $this->findOneBy([
            'email' => strtolower(trim($email)),
        ]);
    }
}
