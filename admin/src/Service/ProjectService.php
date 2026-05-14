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

        // First try the literal project id (e.g. "project1") so explicitly
        // created DB rows win over any package-prefixed discovery.
        if ($domain) {
            $found = $this->projectRepository->findOneByIdForDomain($projectId, (string) $domain->getId());
            if ($found) {
                return $found;
            }
        } else {
            $found = $this->projectRepository->findOneByFullOrCleanId($projectId);
            if ($found) {
                return $found;
            }
        }

        // If the literal id didn't match, resolve short subdomain names like
        // "project1" to package-prefixed project ids such as "core/project1"
        // when there is exactly one match among discovered package entries.
        if (strpos($projectId, '/') === false) {
            $packages = $this->packageDiscoveryService->discoverEntryDirectories('projects');
            $matches = [];
            foreach (array_keys($packages) as $pkgId) {
                $parts = explode('/', $pkgId);
                if (end($parts) === $projectId) {
                    $matches[] = $pkgId;
                }
            }
            if (count($matches) === 1) {
                $resolved = $matches[0];
                if ($domain) {
                    return $this->projectRepository->findOneByIdForDomain($resolved, (string) $domain->getId());
                }

                return $this->projectRepository->find($resolved);
            }
        }

        return null;

    }

    /**
     * @param (callable(list<string>): bool)|null $confirmProjectCreation
     *
     * @return array{created: list<array{id: string}>}
     */
    public function sync(?callable $confirmProjectCreation = null): array
    {
        $this->assertNoDuplicateProjects();

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

    private function assertNoDuplicateProjects(): void
    {
        $duplicates = $this->projectRepository->findDuplicateCleanIdGroups();
        if ($duplicates === []) {
            return;
        }

        $details = array_map(
            static fn (array $group): string => sprintf('%s: %s', $group['cleanId'], implode(', ', $group['ids'])),
            $duplicates,
        );

        throw new \RuntimeException(sprintf(
            'Duplicate project ids detected: %s',
            implode('; ', $details),
        ));
    }
}
