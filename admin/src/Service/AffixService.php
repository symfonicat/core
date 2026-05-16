<?php

namespace Symfonicat\Service;

use Symfonicat\Service\DomainService;
use Pdp\Domain;
use Pdp\Rules;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class AffixService
{

    public function __construct(

        private readonly string $subdomainDir,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
        private readonly DomainService $domainService

    ) {
    }

    /**
     * @return list<string>
     */
    public function getAffixesRaw () : array
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

            $affix = trim ( $result->subDomain()->toString(), '.');
            if ( $affix === '') {
                return [];
            }

            return array_values ( array_reverse ( explode ('.', $affix)));
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());

            return [];
        }
    }

    /**
     * @return list<string>
     */
    public function getAffixes () : array
    {
        $raw = $this->getAffixesRaw();

        if (
            
            isset($raw[0]) &&
            $raw[0] === 'www'

        ) array_shift($raw);

        return array_values($raw);
    }

    public function getAffixByIndex (int $index) : string | NULL
    {
        return $this->getAffixes()[$index] ?? NULL;
    }
}
