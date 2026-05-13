<?php

namespace Symfonicat\Repository;

use Symfonicat\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    //    /**
    //     * @return Project[] Returns an array of Project objects
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

    public function findOneByIdForDomain(string $id, string $domainId): ?Project
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $qb = $this->createQueryBuilder('project')
            ->innerJoin('project.domains', 'd')
            ->andWhere('d.id = :domainId')
            ->setParameter('domainId', $domainId)
            ->orderBy('CASE WHEN project.id = :id THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('project.id', 'ASC');

        // Match either exact id or package-prefixed id that ends with "/{id}".
        $qb->andWhere($qb->expr()->orX('project.id = :id', 'project.id LIKE :idSuffix'))
            ->setParameter('id', $id)
            ->setParameter('idSuffix', '%/'.$id);

        $projects = $qb->getQuery()->getResult();

        return $this->singleOrAmbiguousProject($projects, $id);
    }

    public function findOneByFullOrCleanId(string $id): ?Project
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $projects = $this->createQueryBuilder('project')
            ->andWhere('project.id = :id OR project.id LIKE :idSuffix')
            ->setParameter('id', $id)
            ->setParameter('idSuffix', '%/'.$id)
            ->orderBy('CASE WHEN project.id = :id THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('project.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->singleOrAmbiguousProject($projects, $id);
    }

    /**
     * @return list<array{cleanId: string, ids: list<string>}>
     */
    public function findDuplicateCleanIdGroups(): array
    {
        $groups = [];

        foreach ($this->findAllOrderedById() as $project) {
            $cleanId = trim((string) $project->getId());
            $fullId = trim((string) $project->getId(true));

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
     * @return Project[]
     */
    public function findAllOrderedById(): array
    {
        return $this->createQueryBuilder('project')
            ->orderBy('project.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<Project> $projects
     */
    private function singleOrAmbiguousProject(array $projects, string $lookupId): ?Project
    {
        $projects = array_values(array_filter($projects, static fn ($project): bool => $project instanceof Project));

        if ($projects === []) {
            return null;
        }

        if (count($projects) > 1 && !str_contains($lookupId, '/')) {
            $matches = array_map(
                static fn (Project $project): string => (string) $project->getId(true),
                $projects,
            );

            throw new \RuntimeException(sprintf(
                'Project id "%s" is ambiguous. Matching ids: %s',
                $lookupId,
                implode(', ', $matches),
            ));
        }

        return $projects[0];
    }
}
