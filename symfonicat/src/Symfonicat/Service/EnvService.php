<?php

namespace Symfonicat\Service;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Project;
use Symfonicat\Entity\DomainEnv;
use Symfonicat\Entity\ProjectEnv;

final class EnvService
{
    public function __construct(
        private readonly DomainService $domainService,
        private readonly ProjectService $projectService,
    ) {
    }

    public function get(string $id, Domain|Project|null $entity = null): ?string
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        if ($entity instanceof Domain) {
            return $this->findDomainValue($id, $entity);
        }

        if ($entity instanceof Project) {
            return $this->mergeProjectValue($id, $entity, $this->resolveDomainForProject($entity));
        }

        $domain = $this->domainService->load();
        $project = $this->projectService->load();

        if ($project instanceof Project) {
            return $this->mergeProjectValue($id, $project, $domain);
        }

        return $this->findDomainValue($id, $domain);
    }

    private function mergeProjectValue(string $id, Project $project, ?Domain $domain): ?string
    {
        $value = $this->findDomainValue($id, $domain);
        $projectValue = $this->findProjectValue($id, $project);

        return $projectValue ?? $value;
    }

    private function resolveDomainForProject(Project $project): ?Domain
    {
        $domain = $this->domainService->load();

        if ($domain instanceof Domain && $project->hasDomain($domain)) {
            return $domain;
        }

        return null;
    }

    private function findDomainValue(string $id, ?Domain $domain): ?string
    {
        if (!$domain instanceof Domain) {
            return null;
        }

        foreach ($domain->getEnv() as $item) {
            if (!$item instanceof DomainEnv) {
                continue;
            }

            if ($item->getEnv()?->getId() === $id) {
                return $item->getValue();
            }
        }

        return null;
    }

    private function findProjectValue(string $id, ?Project $project): ?string
    {
        if (!$project instanceof Project) {
            return null;
        }

        foreach ($project->getEnv() as $item) {
            if (!$item instanceof ProjectEnv) {
                continue;
            }

            if ($item->getEnv()?->getId() === $id) {
                return $item->getValue();
            }
        }

        return null;
    }
}
