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

        $applications = $this->createQueryBuilder('application')
            ->andWhere('application.id = :id OR application.id LIKE :idSuffix')
            ->setParameter('id', $id)
            ->setParameter('idSuffix', '%/'.$id)
            ->orderBy('CASE WHEN application.id = :id THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('application.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->singleOrAmbiguousApplication($applications, $id);
    }

    /**
     * @return list<array{cleanId: string, ids: list<string>}>
     */
    public function findDuplicateCleanIdGroups(): array
    {
        $groups = [];

        foreach ($this->findAllOrderedById() as $application) {
            $cleanId = trim((string) $application->getId());
            $fullId = trim((string) $application->getId(true));

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
     * @param list<Application> $applications
     */
    private function singleOrAmbiguousApplication(array $applications, string $lookupId): ?Application
    {
        $applications = array_values(array_filter($applications, static fn ($application): bool => $application instanceof Application));

        if ($applications === []) {
            return null;
        }

        if (count($applications) > 1 && !str_contains($lookupId, '/')) {
            $matches = array_map(
                static fn (Application $application): string => (string) $application->getId(true),
                $applications,
            );

            throw new \RuntimeException(sprintf(
                'Application id "%s" is ambiguous. Matching ids: %s',
                $lookupId,
                implode(', ', $matches),
            ));
        }

        return $applications[0];
    }
}
