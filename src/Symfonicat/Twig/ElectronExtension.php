<?php

namespace Symfonicat\Twig;

use Symfonicat\Entity\Application;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Electron;
use Symfonicat\Entity\Project;
use Symfonicat\Repository\ElectronRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class ElectronExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly ElectronRepository $electronRepository,
        private readonly RequestStack $requestStack,
        private readonly string $assetBaseUrl = '',
    ) {
    }

    public function getGlobals(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return [
                'electron' => false,
                'electron_icon' => null,
                'asset_base_url' => '',
            ];
        }

        $electron = $this->isElectronRequest($request);
        $electronConfig = $electron ? $this->resolveElectronConfig($request) : null;
        $electronIcon = $electronConfig?->getFavicon();

        return [
            'electron' => $electron,
            'electron_icon' => (is_string($electronIcon) && $electronIcon !== '') ? $electronIcon : null,
            'asset_base_url' => $this->resolveAssetBaseUrl(),
        ];
    }

    private function isElectronRequest(Request $request): bool
    {
        if ($request->query->has('electron')) {
            $raw = strtolower(trim((string) $request->query->get('electron', '')));
            return !in_array($raw, ['0', 'false', 'no', 'off'], true);
        }

        if ($request->hasSession()) {
            return (bool) $request->getSession()->get('is_electron_app', false);
        }

        return false;
    }

    private function resolveAssetBaseUrl(): string
    {
        $base = trim($this->assetBaseUrl);
        if ($base !== '') {
            return rtrim($base, '/');
        }

        return '';
    }

    private function resolveElectronConfig(Request $request): ?Electron
    {
        $application = $request->attributes->get('application');
        if ($application instanceof Application) {
            return $this->electronRepository->findOneForApplication($application);
        }

        $project = $request->attributes->get('project');
        if ($project instanceof Project) {
            return $this->electronRepository->findOneForProject($project);
        }

        $domain = $request->attributes->get('domain');
        if ($domain instanceof Domain) {
            return $this->electronRepository->findOneForDomain($domain);
        }

        return null;
    }
}
