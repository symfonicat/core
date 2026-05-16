<?php

namespace Symfonicat\Repository;

use Symfonicat\Entity\Subdomain;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subdomain>
 */
class SubdomainRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subdomain::class);
    }

    //    /**
    //     * @return Subdomain[] Returns an array of Subdomain objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    public function findOneByIdForDomain(string $id, string $domainId): ?Subdomain
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $qb = $this->createQueryBuilder('subdomain')
            ->innerJoin('subdomain.domains', 'd')
            ->andWhere('d.id = :domainId')
            ->setParameter('domainId', $domainId)
            ->orderBy('CASE WHEN subdomain.id = :id THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('subdomain.id', 'ASC');

        // Match either exact id or package-prefixed id that ends with "/{id}".
        $qb->andWhere($qb->expr()->orX('subdomain.id = :id', 'subdomain.id LIKE :idSuffix'))
            ->setParameter('id', $id)
            ->setParameter('idSuffix', '%/'.$id);

        $subdomains = $qb->getQuery()->getResult();

        return $this->singleOrAmbiguousSubdomain($subdomains, $id);
    }

    public function findOneByFullOrCleanId(string $id): ?Subdomain
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $subdomains = $this->createQueryBuilder('subdomain')
            ->andWhere('subdomain.id = :id OR subdomain.id LIKE :idSuffix')
            ->setParameter('id', $id)
            ->setParameter('idSuffix', '%/'.$id)
            ->orderBy('CASE WHEN subdomain.id = :id THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('subdomain.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->singleOrAmbiguousSubdomain($subdomains, $id);
    }

    /**
     * @return list<array{cleanId: string, ids: list<string>}>
     */
    public function findDuplicateCleanIdGroups(): array
    {
        $groups = [];

        foreach ($this->findAllOrderedById() as $subdomain) {
            $cleanId = trim((string) $subdomain->getId(false));
            $fullId = trim((string) $subdomain->getId());

            if ($cleanId === '' || $fullId === '') {
                continue;
            }

            $groups[$cleanId][] = $fullId;
        }

        $duplicates = [];

        foreach ($groups as $cleanId => $ids) {
            $ids = array_values(array_unique($ids));
            if (count($ids) < 2) {
                continue;
            }

            $duplicates[] = [
                'cleanId' => $cleanId,
                'ids' => $ids,
            ];
        }

        return $duplicates;
    }

    /**
     * @return Subdomain[]
     */
    public function findAllOrderedById(): array
    {
        return $this->createQueryBuilder('subdomain')
            ->orderBy('subdomain.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<Subdomain> $subdomains
     */
    private function singleOrAmbiguousSubdomain(array $subdomains, string $lookupId): ?Subdomain
    {
        $subdomains = array_values(array_filter($subdomains, static fn ($subdomain): bool => $subdomain instanceof Subdomain));

        if ($subdomains === []) {
            return null;
        }

        if (count($subdomains) > 1 && !str_contains($lookupId, '/')) {
            $matches = array_map(
                static fn (Subdomain $subdomain): string => (string) $subdomain->getId(),
                $subdomains,
            );

            throw new \RuntimeException(sprintf(
                'Subdomain id "%s" is ambiguous. Matching ids: %s',
                $lookupId,
                implode(', ', $matches),
            ));
        }

        return $subdomains[0];
    }
}
