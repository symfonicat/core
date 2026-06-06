<?php

namespace Symfonicat\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class AffixService
{

    public function __construct(
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
            if (!is_string($host)) {
                return [];
            }

            $host = strtolower(trim($host));
            if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
                return [];
            }

            $domain = $this->domainService->host();
            if (!is_string($domain) || $domain === '') {
                return [];
            }

            $domain = strtolower(trim($domain));
            if ($domain === '' || $host === $domain) {
                return [];
            }

            if (!str_ends_with($host, '.'.$domain)) {
                return [];
            }

            $affix = substr($host, 0, -strlen($domain) - 1);
            if (!is_string($affix)) {
                return [];
            }

            $affix = trim($affix, '.');
            if ($affix === '') {
                return [];
            }

            $segments = array_values(array_filter(explode('.', $affix), static fn (string $label): bool => $label !== ''));
            if ($segments === []) {
                return [];
            }

            return array_values(array_reverse($segments));
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
