<?php

namespace Symfonicat\Twig;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Project;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\ProjectService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class RuntimeAssetExtension extends AbstractExtension
{
    public function __construct(
        private readonly DomainService $domainService,
        private readonly ProjectService $projectService,
        private readonly RequestStack $requestStack,
        private readonly string $projectDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('symfonicat_asset', $this->asset(...)),
        ];
    }

    private function base(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return '/default/';
        }

        if ($request->getPathInfo() === '/admin' || str_starts_with($request->getPathInfo(), '/admin/')) {
            return '/default/';
        }

        $project = $this->projectService->load();
        if ($project instanceof Project && $this->assetDirectoryExists('projects', (string) $project->getId())) {
            return sprintf('/projects/%s/', $this->encodePath((string) $project->getId()));
        }

        $domain = $this->domainService->load();
        if ($domain instanceof Domain && $this->assetDirectoryExists('domains', (string) $domain->getId())) {
            return sprintf('/domains/%s/', $this->encodePath((string) $domain->getId()));
        }

        return '/default/';
    }

    public function asset(string $path = ''): string
    {
        $path = trim($path, " \t\n\r\0\x0B/");

        return $path === ''
            ? $this->base()
            : $this->base().$this->encodePath($path);
    }

    private function encodePath(string $path): string
    {
        $segments = array_values(array_filter(
            explode('/', trim($path, " \t\n\r\0\x0B/")),
            static fn (string $segment): bool => $segment !== '',
        ));

        return implode('/', array_map(rawurlencode(...), $segments));
    }

    private function assetDirectoryExists(string $type, string $id): bool
    {
        $path = sprintf(
            '%s/public/%s/%s',
            rtrim($this->projectDir, '/'),
            trim($type, '/'),
            trim($id, " \t\n\r\0\x0B/"),
        );

        return is_dir($path);
    }
}
