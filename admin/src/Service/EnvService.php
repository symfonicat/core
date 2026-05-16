<?php

namespace Symfonicat\Service;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Subdomain;
use Symfonicat\Entity\DomainEnv;
use Symfonicat\Entity\Electron;
use Symfonicat\Entity\ElectronEnv;
use Symfonicat\Entity\SubdomainEnv;

final class EnvService
{
    public function __construct(
        private readonly DomainService $domainService,
        private readonly ElectronService $electronService,
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
                $this->collectDomainValues($entity),
            );
        }

        if ($entity instanceof Subdomain) {
            return $this->mergeValues(
                $this->collectDomainValues($this->resolveDomainForSubdomain($entity)),
                $this->collectSubdomainValues($entity),
                $this->collectElectronValues($this->electronService->loadForContext(null, $this->resolveDomainForSubdomain($entity), $entity)),
            );
        }

        $domain = $this->domainService->load();
        $subdomain = $this->subdomainService->load();
        $electron = $this->electronService->load();

        if ($subdomain instanceof Subdomain) {
            return $this->mergeValues(
                $this->collectDomainValues($domain),
                $this->collectSubdomainValues($subdomain),
                $this->collectElectronValues($electron),
            );
        }

        return $this->mergeValues(
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
