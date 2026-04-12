<?php

namespace Symfonicat\Service;

use Symfonicat\Service\DomainService;
use Pdp\Domain;
use Pdp\Rules;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class SubdomainService
{

    public function __construct(

        private readonly string $projectDir,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
        private readonly DomainService $domainService

    ) {
    }

    /**
     * @return list<string>
     */
    public function getSubdomainsRaw () : array
    {
        try {
            $host = $this->requestStack->getCurrentRequest()?->getHost();
            if ( $host === NULL) {
                return [];
            }

            if ( str_ends_with($host, 'localhost')) {
                $host = str_replace ('localhost', '', $host) . 'localhost.com';
            }

            $domain = Domain::fromIDNA2008($host);
            $result = $this->domainService->getPublicSuffixList()->resolve($domain);

            $subdomain = trim ( $result->subDomain()->toString(), '.');
            if ( $subdomain === '') {
                return [];
            }

            return array_values ( array_reverse ( explode ('.', $subdomain)));
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());

            return [];
        }
    }

    /**
     * @return list<string>
     */
    public function getSubdomains () : array
    {
        $raw = $this->getSubdomainsRaw();

        if (
            
            isset($raw[0]) &&
            $raw[0] === 'www'

        ) array_shift($raw);

        return array_values($raw);
    }

    public function getSubdomainByIndex (int $index) : string | NULL
    {
        return $this->getSubdomains()[$index] ?? NULL;
    }
}
