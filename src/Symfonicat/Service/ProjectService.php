<?php

namespace Symfonicat\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Project;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\SubdomainService;
use Symfonicat\Repository\ProjectRepository;

class ProjectService
{

    public function __construct (

        private readonly DomainService $domainService,
        private readonly SubdomainService $subdomainService,
        private readonly ProjectRepository $projectRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PackageDiscoveryService $packageDiscoveryService,

    ) {
    }

    public function load () {
        $projectId = $this->subdomainService->getSubdomainByIndex(0);
        $domain = $this->domainService->load();

        if ($projectId === NULL || $projectId === '') {
            return null;
        }

        if ($domain) {
            return $this->projectRepository->findOneByIdForDomain($projectId, $domain->getId());
        }

        return $this->projectRepository->find($projectId);

    }

    /**
     * @param (callable(list<string>): bool)|null $confirmProjectCreation
     *
     * @return array{created: list<array{id: string}>}
     */
    public function sync(?callable $confirmProjectCreation = null): array
    {
        $packageProjects = $this->discoverPackageProjects();
        $databaseProjects = $this->indexDatabaseProjects();

        $missingProjectIds = array_values(array_diff($packageProjects, array_keys($databaseProjects)));
        sort($missingProjectIds, SORT_STRING);

        if ($missingProjectIds === []) {
            return ['created' => []];
        }

        if ($confirmProjectCreation !== null && !(bool) $confirmProjectCreation($missingProjectIds)) {
            throw new \RuntimeException('Aborted creating missing project rows.');
        }

        $created = [];

        foreach ($missingProjectIds as $projectId) {
            $project = (new Project())->setId($projectId);

            $this->entityManager->persist($project);
            $created[] = ['id' => $projectId];
        }

        $this->entityManager->flush();

        return ['created' => $created];
    }

    /**
     * @return list<string>
     */
    private function discoverPackageProjects(): array
    {
        return array_keys($this->packageDiscoveryService->discoverEntryDirectories('projects'));
    }

    /**
     * @return array<string, Project>
     */
    private function indexDatabaseProjects(): array
    {
        $projects = [];

        foreach ($this->projectRepository->findAllOrderedById() as $project) {
            $projectId = $project->getId();
            if ($projectId === null || $projectId === '') {
                continue;
            }

            $projects[$projectId] = $project;
        }

        return $projects;
    }
}
