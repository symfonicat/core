<?php

namespace Symfonicat\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Subdomain;
use Symfonicat\Repository\SubdomainRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class SubdomainService
{

    public function __construct (

        private readonly DomainService $domainService,
        private readonly AffixService $affixService,
        private readonly SubdomainRepository $subdomainRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RuntimeConfig $runtimeConfig,
        private readonly RequestStack $requestStack,

    ) {
    }

    public function load () {
        $affix = $this->affixService->getAffixByIndex(0);
        $domain = $this->domainService->load();

        if ($affix === NULL || $affix === '') {
            return null;
        }

        if ($this->isCoreRoute()) {
            if ($domain) {
                $found = $this->subdomainRepository->findOneByAffixForDomain($affix, (string) $domain->getTld());
                if ($found) {
                    return $found;
                }
            } else {
                $found = $this->subdomainRepository->findOneByAffix($affix);
                if ($found) {
                    return $found;
                }
            }
        }

        if ($domain) {
            $found = $this->runtimeConfig->subdomainByAffixForDomain($affix, $domain);
            if ($found) {
                return $found;
            }
        } else {
            $found = $this->runtimeConfig->subdomainByAffix($affix);
            if ($found) {
                return $found;
            }
        }

        return null;

    }

    /**
     * @param (callable(list<string>): bool)|null $confirmSubdomainCreation
     *
     * @return array{created: list<array{affix: string, domain: string}>}
     */
    public function sync(?callable $confirmSubdomainCreation = null): array
    {
        $packageSubdomains = $this->discoverConfiguredSubdomains();
        $databaseSubdomains = $this->indexDatabaseSubdomains();

        $missingSubdomainKeys = array_values(array_diff($packageSubdomains, array_keys($databaseSubdomains)));
        sort($missingSubdomainKeys, SORT_STRING);

        if ($missingSubdomainKeys === []) {
            return ['created' => []];
        }

        if ($confirmSubdomainCreation !== null && !(bool) $confirmSubdomainCreation($missingSubdomainKeys)) {
            throw new \RuntimeException('Aborted creating missing subdomain rows.');
        }

        $created = [];

        foreach ($missingSubdomainKeys as $subdomainKey) {
            [$domainId, $affix] = $this->splitSubdomainKey($subdomainKey);
            $subdomain = (new Subdomain())
                ->setAffix($affix)
                ->setDomain($domainId === '' ? null : $this->runtimeConfig->domainByHost($domainId));

            $this->entityManager->persist($subdomain);
            $created[] = ['affix' => $affix, 'domain' => $domainId];
        }

        $this->entityManager->flush();

        return ['created' => $created];
    }

    /**
     * @return list<string>
     */
    private function discoverConfiguredSubdomains(): array
    {
        return array_values(array_filter(array_map(
            function (Subdomain $subdomain): ?string {
                $affix = trim((string) $subdomain->getAffix());
                if ($affix === '') {
                    return null;
                }

                $domainId = trim((string) $subdomain->getDomain()?->getTld());

                return $this->subdomainKey($domainId === '' ? null : $subdomain->getDomain(), $affix);
            },
            $this->runtimeConfig->subdomains(),
        )));
    }

    /**
     * @return array<string, Subdomain>
     */
    private function indexDatabaseSubdomains(): array
    {
        $subdomains = [];

        foreach ($this->subdomainRepository->findAllOrderedByAffix() as $subdomain) {
            $affix = trim((string) $subdomain->getAffix());
            if ($affix === '') {
                continue;
            }

            $subdomains[$this->subdomainKey($subdomain->getDomain(), $affix)] = $subdomain;
        }

        return $subdomains;
    }

    private function subdomainKey(?Domain $domain, string $affix): string
    {
        $domainId = trim((string) $domain?->getId());

        return ($domainId === '' ? '' : $domainId).'|'.trim($affix);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitSubdomainKey(string $key): array
    {
        [$domainId, $affix] = array_pad(explode('|', $key, 2), 2, '');

        return [trim($domainId), trim($affix)];
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
