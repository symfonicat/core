<?php

namespace Symfonicat\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Repository\DomainRepository;
use Pdp\Domain;
use Pdp\Rules;
use Symfony\Component\HttpFoundation\RequestStack;

class DomainService
{
    public function __construct(
        private readonly string $subdomainDir,
        private readonly RequestStack $requestStack,
        private readonly DomainRepository $domainRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PackageDiscoveryService $packageDiscoveryService,
        private readonly RuntimeConfig $runtimeConfig,
    ) {
    }

    public function load() : ? \Symfonicat\Entity\Domain
    {
        $host = $this->host();
        if ($host === null || $host === '') {
            return null;
        }

        if (($_SERVER['APP_ENV'] ?? null) === 'test') {
            return $this->domainRepository->findOneByHost($host);
        }

        return $this->runtimeConfig->domainByHost($host);
    }

    public function host() : ?string
    {
        $host = $this->requestStack->getCurrentRequest()?->getHost();
        if ($host === null) {
            return null;
        }

        $host = strtolower(trim($host));
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return 'localhost';
        }

        $domain = Domain::fromIDNA2008($host);
        $result = $this->getPublicSuffixList()->resolve($domain);

        $registrable = $result->registrableDomain()->toString();
        if ($registrable === '') {
            return null;
        }

        return $registrable;
    }

    public function getPublicSuffixList(): Rules
    {
        static $list = null;
        if ($list === null) {
            $list = Rules::fromPath($this->subdomainDir . '/public_suffix_list.dat');
        }

        return $list;
    }

    /**
     * @param (callable(list<string>): bool)|null $confirmDomainCreation
     *
     * @return array{created: list<array{id: string}>}
     */
    public function sync(?callable $confirmDomainCreation = null): array
    {
        $packageDomains = $this->discoverPackageDomains();
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
    private function discoverPackageDomains(): array
    {
        return array_keys($this->packageDiscoveryService->discoverEntryDirectories('domain'));
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
}
