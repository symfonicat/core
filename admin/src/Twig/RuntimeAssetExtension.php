<?php

namespace Symfonicat\Twig;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Application;
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

    private function base(Application|Domain|Project|null $context = null, string $path = ''): string
    {
        if ($context instanceof Application) {
            $applicationId = trim((string) $context->getId());
            if ($applicationId !== '') {
                return sprintf('/%s/', $this->encodePath($applicationId));
            }

            return '/default/';
        }

        if ($context instanceof Project) {
            $projectId = trim((string) $context->getId(false));
            if ($projectId !== '') {
                return sprintf('/projects/%s/', $this->encodePath($projectId));
            }

            return '/default/';
        }

        if ($context instanceof Domain) {
            $domainId = trim((string) $context->getId(false));
            if ($domainId !== '') {
                return sprintf('/domains/%s/', $this->encodePath($domainId));
            }

            return '/default/';
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return $this->defaultAssetBase($path);
        }

        if ($request->getPathInfo() === '/admin' || str_starts_with($request->getPathInfo(), '/admin/')) {
            return $this->defaultAssetBase($path);
        }

        $project = $this->projectService->load();
        if ($project instanceof Project && $this->assetFileExists('projects', (string) $project->getId(false), $path)) {
            return sprintf('/projects/%s/', $this->encodePath((string) $project->getId(false)));
        }

        $domain = $this->domainService->load();
        if ($domain instanceof Domain && $this->assetFileExists('domains', (string) $domain->getId(false), $path)) {
            return sprintf('/domains/%s/', $this->encodePath((string) $domain->getId(false)));
        }

        return $this->defaultAssetBase($path);
    }

    public function asset(string $path = '', Application|Domain|Project|null $context = null): string
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
        $directory = rtrim($this->projectDir, '/').'/public/'.trim($type, '/');

        if (trim($id, " \t\n\r\0\x0B/") !== '') {
            $directory .= '/'.trim($id, " \t\n\r\0\x0B/");
        }

        $path = sprintf('%s/%s', $directory, $path);

        return is_file($path);
    }

    private function defaultAssetBase(string $path): string
    {
        if ($path !== '' && !$this->assetFileExists('default', '', $path)) {
            throw new \RuntimeException(sprintf('Asset "%s" was not found in the project, domain, or default public folders.', $path));
        }

        return '/default/';
    }
}
