<?php

namespace Symfonicat\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Domain as DomainEntity;
use Symfonicat\Repository\DomainRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class DomainService
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly DomainRepository $domainRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RuntimeConfig $runtimeConfig,
    ) {
    }

    public function load() : ? \Symfonicat\Entity\Domain
    {
        $host = $this->host();
        if ($host === null || $host === '') {
            return null;
        }

        if ($this->isCoreRoute()) {
            return $this->domainRepository->findOneByHost($host);
        }

        return $this->runtimeConfig->domainByHost($host);
    }

    public function host() : ?string
    {
        $host = $this->requestStack->getCurrentRequest()?->getHost();
        if (!is_string($host)) {
            return null;
        }

        $host = strtolower(trim($host));
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        $domain = $this->matchConfiguredDomain($host);
        if ($domain !== null) {
            return $domain;
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return 'localhost';
        }

        return null;
    }

    /**
     * @param (callable(list<string>): bool)|null $confirmDomainCreation
     *
     * @return array{created: list<array{id: string}>}
     */
    public function sync(?callable $confirmDomainCreation = null): array
    {
        $packageDomains = $this->discoverConfiguredDomains();
        $databaseDomains = $this->indexDatabaseDomains();

        $missingDomainIds = array_values(array_diff($packageDomains, array_keys($databaseDomains)));
        sort($missingDomainIds, SORT_STRING);

        if ($missingDomainIds === []) {
            return ['created' => []];
        }

        if ($confirmDomainCreation !== null && !(bool) $confirmDomainCreation($missingDomainIds)) {
            throw new \RuntimeException('Aborted creating missing domain rows.');
        }

        $created = [];

        foreach ($missingDomainIds as $domainId) {
            $domain = (new \Symfonicat\Entity\Domain())->setId($domainId);

            $this->entityManager->persist($domain);
            $created[] = ['id' => $domainId];
        }

        $this->entityManager->flush();

        return ['created' => $created];
    }

    /**
     * @return list<string>
     */
    private function discoverConfiguredDomains(): array
    {
        return array_map(
            static fn (DomainEntity $domain): string => trim((string) $domain->getTld()),
            $this->runtimeConfig->domains(),
        );
    }

    /**
     * @return list<string>
     */
    private function configuredDomains(): array
    {
        $domains = [];

        foreach ($this->runtimeConfig->domains() as $domain) {
            $domainId = strtolower(trim((string) $domain->getTld()));
            if ($domainId !== '') {
                $domains[] = $domainId;
            }
        }

        usort($domains, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        return $domains;
    }

    private function matchConfiguredDomain(string $host): ?string
    {
        foreach ($this->configuredDomains() as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return $domain;
            }
        }

        return null;
    }

    /**
     * @return array<string, \Symfonicat\Entity\Domain>
     */
    private function indexDatabaseDomains(): array
    {
        $domains = [];

        foreach ($this->domainRepository->findAllOrderedById() as $domain) {
            $domainId = $domain->getId();
            if ($domainId === null || $domainId === '') {
                continue;
            }

            $domains[$domainId] = $domain;
        }

        return $domains;
    }

    private function isCoreRoute(): bool
    {
        $path = $this->requestStack->getCurrentRequest()?->getPathInfo();
        if (!is_string($path)) {
            return false;
        }

        return $path === '/core' || str_starts_with($path, '/core/');
    }
}
