<?php

namespace Symfonicat\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Subdomain;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\AffixService;
use Symfonicat\Repository\SubdomainRepository;

class SubdomainService
{

    public function __construct (

        private readonly DomainService $domainService,
        private readonly AffixService $affixService,
        private readonly SubdomainRepository $subdomainRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PackageDiscoveryService $packageDiscoveryService,
        private readonly RuntimeConfig $runtimeConfig,

    ) {
    }

    public function load () {
        $subdomainId = $this->affixService->getAffixByIndex(0);
        $domain = $this->domainService->load();

        if ($subdomainId === NULL || $subdomainId === '') {
            return null;
        }

        // First try the literal subdomain id (e.g. "subdomain1") so explicitly
        // created DB rows win over any package-prefixed discovery.
        if (($_SERVER['APP_ENV'] ?? null) === 'test') {
            if ($domain) {
                $found = $this->subdomainRepository->findOneByIdForDomain($subdomainId, (string) $domain->getId());
                if ($found) {
                    return $found;
                }
            } else {
                $found = $this->subdomainRepository->findOneById($subdomainId);
                if ($found) {
                    return $found;
                }
            }
        }

        if ($domain) {
            $found = $this->runtimeConfig->subdomainByIdForDomain($subdomainId, $domain);
            if ($found) {
                return $found;
            }
        } else {
            $found = $this->runtimeConfig->subdomainById($subdomainId);
            if ($found) {
                return $found;
            }
        }

        return null;

    }

    /**
     * @param (callable(list<string>): bool)|null $confirmSubdomainCreation
     *
     * @return array{created: list<array{id: string}>}
     */
    public function sync(?callable $confirmSubdomainCreation = null): array
    {
        $packageSubdomains = $this->discoverPackageSubdomains();
        $databaseSubdomains = $this->indexDatabaseSubdomains();

        $missingSubdomainIds = array_values(array_diff($packageSubdomains, array_keys($databaseSubdomains)));
        sort($missingSubdomainIds, SORT_STRING);

        if ($missingSubdomainIds === []) {
            return ['created' => []];
        }

        if ($confirmSubdomainCreation !== null && !(bool) $confirmSubdomainCreation($missingSubdomainIds)) {
            throw new \RuntimeException('Aborted creating missing subdomain rows.');
        }

        $created = [];

        foreach ($missingSubdomainIds as $subdomainId) {
            $subdomain = (new Subdomain())->setId($subdomainId);

            $this->entityManager->persist($subdomain);
            $created[] = ['id' => $subdomainId];
        }

        $this->entityManager->flush();

        return ['created' => $created];
    }

    /**
     * @return list<string>
     */
    private function discoverPackageSubdomains(): array
    {
        return array_keys($this->packageDiscoveryService->discoverEntryDirectories('subdomain'));
    }

    /**
     * @return array<string, Subdomain>
     */
    private function indexDatabaseSubdomains(): array
    {
        $subdomains = [];

        foreach ($this->subdomainRepository->findAllOrderedById() as $subdomain) {
            $subdomainId = $subdomain->getId();
            if ($subdomainId === null || $subdomainId === '') {
                continue;
            }

            $subdomains[$subdomainId] = $subdomain;
        }

        return $subdomains;
    }
}
