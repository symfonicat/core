<?php

namespace Symfonicat\Service;

use Symfonicat\Repository\DomainRepository;
use Pdp\Domain;
use Pdp\Rules;
use Symfony\Component\HttpFoundation\RequestStack;

class DomainService
{
    public function __construct(
        private readonly string $projectDir,
        private readonly RequestStack $requestStack,
        private readonly DomainRepository $domainRepository,
    ) {
    }

    public function load() : ? \Symfonicat\Entity\Domain
    {
        $host = $this->host();
        if ($host === null || $host === '') {
            return null;
        }

        return $this->domainRepository->find($host);
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
            $list = Rules::fromPath($this->projectDir . '/public_suffix_list.dat');
        }

        return $list;
    }
}
