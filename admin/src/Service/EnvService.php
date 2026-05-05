<?php

namespace Symfonicat\Service;

use Symfonicat\Entity\Application;
use Symfonicat\Entity\ApplicationEnv;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Project;
use Symfonicat\Entity\DomainEnv;
use Symfonicat\Entity\Electron;
use Symfonicat\Entity\ElectronEnv;
use Symfonicat\Entity\ProjectEnv;

final class EnvService
{
    public function __construct(
        private readonly ApplicationService $applicationService,
        private readonly DomainService $domainService,
        private readonly ElectronService $electronService,
        private readonly ProjectService $projectService,
    ) {
    }

    public function get(string $id, Application|Domain|Project|null $entity = null): ?string
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $segments = array_values(array_filter(array_map('trim', explode('.', $id))));
        if (count($segments) < 2) {
            return null;
        }

        $value = $this->all($entity);

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return is_string($value) ? $value : null;
    }

    /**
     * @return array<string, array<string, string>>
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
                $this->collectElectronValues($this->electronService->loadForContext(null, $this->resolveDomainForProject($entity), $entity)),
            );
        }

        $application = $this->applicationService->load();
        $domain = $this->domainService->load();
        $project = $this->projectService->load();
        $electron = $this->electronService->load();

        if ($project instanceof Project) {
            return $this->mergeValues(
                $this->collectApplicationValues($application),
                $this->collectDomainValues($domain),
                $this->collectProjectValues($project),
                $this->collectElectronValues($electron),
            );
        }

        return $this->mergeValues(
            $this->collectApplicationValues($application),
            $this->collectDomainValues($domain),
            $this->collectElectronValues($electron),
        );
    }

    /**
     * @param array<string, array<string, string>> ...$valueSets
     *
     * @return array<string, array<string, string>>
     */
    private function mergeValues(array ...$valueSets): array
    {
        if ($valueSets === []) {
            return [];
        }

        return array_replace_recursive(...$valueSets);
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
     * @return array<string, array<string, string>>
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

            $envParentId = $item->getEnv()?->getEnvParent()?->getId();
            $envId = $item->getEnv()?->getId();
            if ($envParentId === null || $envParentId === '' || $envId === null || $envId === '') {
                continue;
            }

            $values[$envParentId][$envId] = $item->getValue();
        }

        return $values;
    }

    /**
     * @return array<string, array<string, string>>
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

            $envParentId = $item->getEnv()?->getEnvParent()?->getId();
            $envId = $item->getEnv()?->getId();
            if ($envParentId === null || $envParentId === '' || $envId === null || $envId === '') {
                continue;
            }

            $values[$envParentId][$envId] = $item->getValue();
        }

        return $values;
    }

    /**
     * @return array<string, array<string, string>>
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

            $envParentId = $item->getEnv()?->getEnvParent()?->getId();
            $envId = $item->getEnv()?->getId();
            if ($envParentId === null || $envParentId === '' || $envId === null || $envId === '') {
                continue;
            }

            $values[$envParentId][$envId] = $item->getValue();
        }

        return $values;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function collectElectronValues(?Electron $electron): array
    {
        if (!$electron instanceof Electron) {
            return [];
        }

        $values = [];

        foreach ($electron->getEnv() as $item) {
            if (!$item instanceof ElectronEnv) {
                continue;
            }

            $envParentId = $item->getEnv()?->getEnvParent()?->getId();
            $envId = $item->getEnv()?->getId();
            if ($envParentId === null || $envParentId === '' || $envId === null || $envId === '') {
                continue;
            }

            $values[$envParentId][$envId] = $item->getValue();
        }

        return $values;
    }
}
