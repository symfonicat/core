<?php

namespace Symfonicat\Twig;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Subdomain;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\SubdomainService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class RuntimeAssetExtension extends AbstractExtension
{
    public function __construct(
        private readonly DomainService $domainService,
        private readonly SubdomainService $subdomainService,
        private readonly RequestStack $requestStack,
        private readonly string $subdomainDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('symfonicat_asset', $this->asset(...)),
        ];
    }

    private function base(Domain|Subdomain|null $context = null, string $path = ''): string
    {
        if ($context instanceof Subdomain) {
            $affix = trim((string) $context->getAffix());
            if ($affix !== '') {
                return sprintf('/subdomain/%s/', $this->encodePath($affix));
            }

            return '/default/';
        }

        if ($context instanceof Domain) {
            $domainId = trim((string) $context->getTld());
            if ($domainId !== '') {
                return sprintf('/domains/%s/', $this->encodePath($domainId));
            }

            return '/default/';
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return $this->defaultAssetBase($path);
        }

        if ($request->getPathInfo() === '/core' || str_starts_with($request->getPathInfo(), '/core/')) {
            return $this->defaultAssetBase($path);
        }

        $subdomain = $this->subdomainService->load();
        if ($subdomain instanceof Subdomain && $this->assetFileExists('subdomain', (string) $subdomain->getAffix(), $path)) {
            return sprintf('/subdomain/%s/', $this->encodePath((string) $subdomain->getAffix()));
        }

        $domain = $this->domainService->load();
        if ($domain instanceof Domain && $this->assetFileExists('domains', (string) $domain->getTld(), $path)) {
            return sprintf('/domains/%s/', $this->encodePath((string) $domain->getTld()));
        }

        return $this->defaultAssetBase($path);
    }

    public function asset(string $path = '', Domain|Subdomain|null $context = null): string
    {
        $path = trim($path, " \t\n\r\0\x0B/");

        return $path === ''
            ? $this->base($context)
            : $this->base($context, $path).$this->encodePath($path);
    }

    private function encodePath(string $path): string
    {
        $segments = array_values(array_filter(
            explode('/', trim($path, " \t\n\r\0\x0B/")),
            static fn (string $segment): bool => $segment !== '',
        ));

        return implode('/', array_map(rawurlencode(...), $segments));
    }

    private function assetFileExists(string $type, string $id, string $path): bool
    {
        $path = trim($path, " \t\n\r\0\x0B/");
        $directory = rtrim($this->subdomainDir, '/').'/public/'.trim($type, '/');

        if (trim($id, " \t\n\r\0\x0B/") !== '') {
            $directory .= '/'.trim($id, " \t\n\r\0\x0B/");
        }

        $path = sprintf('%s/%s', $directory, $path);

        return is_file($path);
    }

    private function defaultAssetBase(string $path): string
    {
        if ($path !== '' && !$this->assetFileExists('default', '', $path)) {
            throw new \RuntimeException(sprintf('Asset "%s" was not found in the subdomain, domain, or default public folders.', $path));
        }

        return '/default/';
    }
}
