<?php

namespace Symfonicat\Service;

use Symfonicat\Entity\Parcel;
use Symfonicat\Entity\ParcelEnv;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Subdomain;
use Symfonicat\Entity\Endpoint;
use Symfonicat\Entity\DomainEnv;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\ApplicationEnv;
use Symfonicat\Entity\SubdomainEnv;
use Symfonicat\Entity\EndpointEnv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class EnvService
{
    public function __construct(
        private readonly DomainService $domainService,
        private readonly ApplicationService $applicationService,
        private readonly SubdomainService $subdomainService,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function get(string $id, Domain|Subdomain|Endpoint|null $entity = null): ?string
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
    public function all(Domain|Subdomain|Endpoint|null $entity = null): array
    {
        if ($entity instanceof Domain) {
            return $this->mergeValues(
                $this->flattenParcelValues($entity->getParcel()),
                $this->flattenDomainValues($entity),
                $this->flattenApplicationValues($this->applicationService->load()),
            );
        }

        if ($entity instanceof Subdomain) {
            $domain = $this->resolveDomainForSubdomain($entity);
            $application = $this->applicationService->loadForContext($domain, $entity);

            return $this->mergeValues(
                $this->flattenParcelValues($domain?->getParcel()),
                $this->flattenDomainValues($domain),
                $this->flattenParcelValues($entity->getParcel()),
                $this->flattenSubdomainValues($entity),
                $this->flattenApplicationValues($application),
            );
        }

        if ($entity instanceof Endpoint) {
            $application = $this->applicationService->load();

            return $this->mergeValues(
                $this->flattenParcelValues($entity->getParcel()),
                $this->flattenEndpointValues($entity),
                $this->flattenApplicationValues($application),
            );
        }

        $domain = $this->domainService->load();
        $subdomain = $this->subdomainService->load();
        $endpoint = $this->endpointFromRequest();
        $application = $this->applicationService->load();

        if ($subdomain instanceof Subdomain) {
            return $this->mergeValues(
                $this->flattenParcelValues($domain?->getParcel()),
                $this->flattenDomainValues($domain),
                $this->flattenParcelValues($subdomain->getParcel()),
                $this->flattenSubdomainValues($subdomain),
                $this->flattenEndpointValues($endpoint),
                $this->flattenApplicationValues($application),
            );
        }

        return $this->mergeValues(
            $this->flattenParcelValues($domain?->getParcel()),
            $this->flattenDomainValues($domain),
            $this->flattenParcelValues($subdomain?->getParcel()),
            $this->flattenSubdomainValues($subdomain),
            $this->flattenEndpointValues($endpoint),
            $this->flattenApplicationValues($application),
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
    public function flattenParcelValues(?Parcel $parcel): array
    {
        if (!$parcel instanceof Parcel) {
            return [];
        }

        $values = [];

        foreach ($parcel->getEnv() as $item) {
            if (!$item instanceof ParcelEnv) {
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
    public function flattenDomainValues(?Domain $domain): array
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
    public function flattenSubdomainValues(?Subdomain $subdomain): array
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
    public function flattenEndpointValues(?Endpoint $endpoint): array
    {
        if (!$endpoint instanceof Endpoint) {
            return [];
        }

        $values = [];

        foreach ($endpoint->getEnv() as $item) {
            if (!$item instanceof EndpointEnv) {
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
    public function flattenApplicationValues(?Application $application): array
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

    private function endpointFromRequest(): ?Endpoint
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return null;
        }

        $endpoint = $request->attributes->get('endpoint');

        return $endpoint instanceof Endpoint ? $endpoint : null;
    }
}
