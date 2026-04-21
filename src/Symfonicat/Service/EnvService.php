<?php

namespace Symfonicat\Service;

use Symfonicat\Entity\Application;
use Symfonicat\Entity\ApplicationEnv;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Project;
use Symfonicat\Entity\DomainEnv;
use Symfonicat\Entity\ProjectEnv;

final class EnvService
{
    public function __construct(
        private readonly ApplicationService $applicationService,
        private readonly DomainService $domainService,
        private readonly ProjectService $projectService,
    ) {
    }

    public function get(string $id, Application|Domain|Project|null $entity = null): ?string
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        return $this->all($entity)[$id] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function all(Application|Domain|Project|null $entity = null): array
    {
        if ($entity instanceof Application) {
            return $this->collectApplicationValues($entity);
        }

        if ($entity instanceof Domain) {
            return $this->mergeValues(
                $this->collectApplicationValues($this->applicationService->load()),
                $this->collectDomainValues($entity),
            );
        }

        if ($entity instanceof Project) {
            return $this->mergeValues(
                $this->collectApplicationValues($this->applicationService->load()),
                $this->collectDomainValues($this->resolveDomainForProject($entity)),
                $this->collectProjectValues($entity),
            );
        }

        $application = $this->applicationService->load();
        $domain = $this->domainService->load();
        $project = $this->projectService->load();

        if ($project instanceof Project) {
            return $this->mergeValues(
                $this->collectApplicationValues($application),
                $this->collectDomainValues($domain),
                $this->collectProjectValues($project),
            );
        }

        return $this->mergeValues(
            $this->collectApplicationValues($application),
            $this->collectDomainValues($domain),
        );
    }

    /**
     * @param array<string, string> ...$valueSets
     *
     * @return array<string, string>
     */
    private function mergeValues(array ...$valueSets): array
    {
        return array_replace(...$valueSets);
    }

    private function resolveDomainForProject(Project $project): ?Domain
    {
        $domain = $this->domainService->load();

        if ($domain instanceof Domain && $project->hasDomain($domain)) {
            return $domain;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function collectApplicationValues(?Application $application): array
    {
        if (!$application instanceof Application) {
            return [];
        }

        $values = [];

        foreach ($application->getEnv() as $item) {
            if (!$item instanceof ApplicationEnv) {
                continue;
            }

            $envId = $item->getEnv()?->getId();
            if ($envId === null || $envId === '') {
                continue;
            }

            $values[$envId] = $item->getValue();
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    private function collectDomainValues(?Domain $domain): array
    {
        if (!$domain instanceof Domain) {
            return [];
        }

        $values = [];

        foreach ($domain->getEnv() as $item) {
            if (!$item instanceof DomainEnv) {
                continue;
            }

            $envId = $item->getEnv()?->getId();
            if ($envId === null || $envId === '') {
                continue;
            }

            $values[$envId] = $item->getValue();
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    private function collectProjectValues(?Project $project): array
    {
        if (!$project instanceof Project) {
            return [];
        }

        $values = [];

        foreach ($project->getEnv() as $item) {
            if (!$item instanceof ProjectEnv) {
                continue;
            }

            $envId = $item->getEnv()?->getId();
            if ($envId === null || $envId === '') {
                continue;
            }

            $values[$envId] = $item->getValue();
        }

        return $values;
    }
}
