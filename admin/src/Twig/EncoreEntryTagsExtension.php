<?php

namespace Symfonicat\Twig;

use Symfonicat\Entity\Bundle;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Module;
use Symfonicat\Entity\Subdomain;
use Symfony\WebpackEncoreBundle\Exception\EntrypointNotFoundException;
use Symfony\WebpackEncoreBundle\Twig\EntryFilesTwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Symfonicat\Service\PackageDiscoveryService;

final class EncoreEntryTagsExtension extends AbstractExtension
{
    public function __construct(
        private readonly EntryFilesTwigExtension $entryFilesTwigExtension,
        private readonly PackageDiscoveryService $packageDiscoveryService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('encore_entry_script_tags_domain', $this->renderDomainScriptTags(...), ['is_safe' => ['html']]),
            new TwigFunction('encore_entry_link_tags_domain', $this->renderDomainLinkTags(...), ['is_safe' => ['html']]),
            new TwigFunction('encore_entry_script_tags_subdomain', $this->renderSubdomainScriptTags(...), ['is_safe' => ['html']]),
            new TwigFunction('encore_entry_link_tags_subdomain', $this->renderSubdomainLinkTags(...), ['is_safe' => ['html']]),
            new TwigFunction('encore_entry_script_tags_endpoint', $this->renderEndpointScriptTags(...), ['is_safe' => ['html']]),
            new TwigFunction('encore_entry_link_tags_endpoint', $this->renderEndpointLinkTags(...), ['is_safe' => ['html']]),
            new TwigFunction('encore_entry_script_tags_module', $this->renderModuleScriptTags(...), ['is_safe' => ['html']]),
            new TwigFunction('encore_entry_link_tags_module', $this->renderModuleLinkTags(...), ['is_safe' => ['html']]),
            new TwigFunction('encore_entry_script_tags_bundle', $this->renderBundleScriptTags(...), ['is_safe' => ['html']]),
            new TwigFunction('encore_entry_link_tags_bundle', $this->renderBundleLinkTags(...), ['is_safe' => ['html']]),
        ];
    }

    public function renderDomainScriptTags(?Domain $domain): string
    {
        return $this->renderScriptTags($this->domainEntryName($domain));
    }

    public function renderDomainLinkTags(?Domain $domain): string
    {
        return $this->renderLinkTags($this->domainEntryName($domain));
    }

    public function renderSubdomainScriptTags(?Subdomain $subdomain): string
    {
        return $this->renderScriptTags($this->subdomainEntryName($subdomain));
    }

    public function renderSubdomainLinkTags(?Subdomain $subdomain): string
    {
        return $this->renderLinkTags($this->subdomainEntryName($subdomain));
    }

    public function renderEndpointScriptTags(?Endpoint $endpoint): string
    {
        return $this->renderScriptTags($this->endpointEntryName($endpoint));
    }

    public function renderEndpointLinkTags(?Endpoint $endpoint): string
    {
        return $this->renderLinkTags($this->endpointEntryName($endpoint));
    }

    public function renderModuleScriptTags(?Module $module): string
    {
        return $this->renderScriptTags($this->moduleEntryName($module));
    }

    public function renderModuleLinkTags(?Module $module): string
    {
        return $this->renderLinkTags($this->moduleEntryName($module));
    }

    public function renderBundleScriptTags(?Bundle $bundle): string
    {
        return $this->renderScriptTags($this->bundleEntryName($bundle));
    }

    public function renderBundleLinkTags(?Bundle $bundle): string
    {
        return $this->renderLinkTags($this->bundleEntryName($bundle));
    }

    private function renderScriptTags(?string $entryName): string
    {
        if ($entryName === null) {
            return '';
        }

        try {
            if (!$this->entryFilesTwigExtension->entryExists($entryName)) {
                return '';
            }

            return $this->entryFilesTwigExtension->renderWebpackScriptTags($entryName);
        } catch (EntrypointNotFoundException) {
            return '';
        }
    }

    private function renderLinkTags(?string $entryName): string
    {
        if ($entryName === null) {
            return '';
        }

        try {
            if (!$this->entryFilesTwigExtension->entryExists($entryName)) {
                return '';
            }

            return $this->entryFilesTwigExtension->renderWebpackLinkTags($entryName);
        } catch (EntrypointNotFoundException) {
            return '';
        }
    }

    private function domainEntryName(?Domain $domain): ?string
    {
        $id = trim((string) $domain?->getId());
        if ($id === '') {
            return null;
        }

        $entryName = 'domain/'.$id;
        if ($this->entryFilesTwigExtension->entryExists($entryName)) {
            return $entryName;
        }

        if (strpos($id, '/') !== false) {
            return $entryName;
        }

        // Do not attempt to discover domain entries from installed packages.
        return $entryName;
    }

    private function subdomainEntryName(?Subdomain $subdomain): ?string
    {
        $id = trim((string) $subdomain?->getId());
        if ($id === '') {
            return null;
        }

        $entryName = 'subdomain/'.$id;
        if ($this->entryFilesTwigExtension->entryExists($entryName)) {
            return $entryName;
        }

        if (strpos($id, '/') !== false) {
            return $entryName;
        }

        $packages = $this->packageDiscoveryService->discoverEntryDirectories('subdomain');
        $matches = [];
        foreach (array_keys($packages) as $pkgId) {
            $parts = explode('/', $pkgId);
            if (end($parts) === $id) {
                $matches[] = $pkgId;
            }
        }

        if (count($matches) === 1) {
            return 'subdomain/'.$matches[0];
        }

        return $entryName;
    }

    private function endpointEntryName(?Endpoint $endpoint): ?string
    {
        $id = trim((string) $endpoint?->getId());
        if ($id === '') {
            return null;
        }

        $entryName = 'endpoint/'.$id;
        if ($this->entryFilesTwigExtension->entryExists($entryName)) {
            return $entryName;
        }

        if (strpos($id, '/') !== false) {
            return $entryName;
        }

        $packages = $this->packageDiscoveryService->discoverEntryDirectories('endpoint');
        $matches = [];
        foreach (array_keys($packages) as $pkgId) {
            $parts = explode('/', $pkgId);
            if (end($parts) === $id) {
                $matches[] = $pkgId;
            }
        }

        if (count($matches) === 1) {
            return 'endpoint/'.$matches[0];
        }

        return $entryName;
    }

    private function moduleEntryName(?Module $module): ?string
    {
        $id = trim((string) $module?->getId());
        if ($id === '') {
            return null;
        }

        $entryName = 'module/'.$id;
        if ($this->entryFilesTwigExtension->entryExists($entryName)) {
            return $entryName;
        }

        if (strpos($id, '/') !== false) {
            return $entryName;
        }

        $packages = $this->packageDiscoveryService->discoverEntryDirectories('module');
        $matches = [];
        foreach (array_keys($packages) as $pkgId) {
            $parts = explode('/', $pkgId);
            if (end($parts) === $id) {
                $matches[] = $pkgId;
            }
        }

        if (count($matches) === 1) {
            return 'module/'.$matches[0];
        }

        return $entryName;
    }

    private function bundleEntryName(?Bundle $bundle): ?string
    {
        $id = trim((string) $bundle?->getId());
        if ($id === '') {
            return null;
        }

        $entryName = 'bundle/'.$id;
        if ($this->entryFilesTwigExtension->entryExists($entryName)) {
            return $entryName;
        }

        if (strpos($id, '/') !== false) {
            return $entryName;
        }

        $packages = $this->packageDiscoveryService->discoverEntryDirectories('bundle');
        $matches = [];
        foreach (array_keys($packages) as $pkgId) {
            $parts = explode('/', $pkgId);
            if (end($parts) === $id) {
                $matches[] = $pkgId;
            }
        }

        if (count($matches) === 1) {
            return 'bundle/'.$matches[0];
        }

        return $entryName;
    }
}
