<?php

namespace Symfonicat\Service;

use Symfonicat\Entity\Bundle;
use Symfonicat\Entity\BundleEnv;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Subdomain;
use Symfonicat\Entity\DomainEnv;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\ApplicationEnv;
use Symfonicat\Entity\SubdomainEnv;

final class EnvService
{
    public function __construct(
        private readonly DomainService $domainService,
        private readonly ApplicationService $applicationService,
        private readonly SubdomainService $subdomainService,
    ) {
    }

    public function get(string $id, Domain|Subdomain|null $entity = null): ?string
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
    public function all(Domain|Subdomain|null $entity = null): array
    {

        if ($entity instanceof Domain) {
            return $this->mergeValues(
                $this->collectBundleValues($entity->getBundle()),
                $this->collectDomainValues($entity),
            );
        }

        if ($entity instanceof Subdomain) {
            $domain = $this->resolveDomainForSubdomain($entity);
            $application = $this->applicationService->loadForContext(null, $domain, $entity);

            return $this->mergeValues(
                $this->collectBundleValues($domain?->getBundle()),
                $this->collectDomainValues($domain),
                $this->collectBundleValues($entity->getBundle()),
                $this->collectSubdomainValues($entity),
                $this->collectApplicationValues($application),
            );
        }

        $domain = $this->domainService->load();
        $subdomain = $this->subdomainService->load();
        $application = $this->applicationService->load();

        if ($subdomain instanceof Subdomain) {
            return $this->mergeValues(
                $this->collectBundleValues($domain?->getBundle()),
                $this->collectDomainValues($domain),
                $this->collectBundleValues($subdomain->getBundle()),
                $this->collectSubdomainValues($subdomain),
                $this->collectApplicationValues($application),
            );
        }

        return $this->mergeValues(
            $this->collectBundleValues($domain?->getBundle()),
            $this->collectDomainValues($domain),
            $this->collectApplicationValues($application),
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

    private function resolveDomainForSubdomain(Subdomain $subdomain): ?Domain
    {
        $domain = $this->domainService->load();

        if ($domain instanceof Domain && $subdomain->hasDomain($domain)) {
            return $domain;
        }

        return null;
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
    private function collectBundleValues(?Bundle $bundle): array
    {
        if (!$bundle instanceof Bundle) {
            return [];
        }

        $values = [];

        foreach ($bundle->getEnv() as $item) {
            if (!$item instanceof BundleEnv) {
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
    private function collectSubdomainValues(?Subdomain $subdomain): array
    {
        if (!$subdomain instanceof Subdomain) {
            return [];
        }

        $values = [];

        foreach ($subdomain->getEnv() as $item) {
            if (!$item instanceof SubdomainEnv) {
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
}
