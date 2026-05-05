<?php

namespace Symfonicat\Twig;

use Symfonicat\Entity\Application;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Module;
use Symfonicat\Entity\Project;
use Symfony\WebpackEncoreBundle\Exception\EntrypointNotFoundException;
use Symfony\WebpackEncoreBundle\Twig\EntryFilesTwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class EncoreEntryTagsExtension extends AbstractExtension
{
    public function __construct(
        private readonly EntryFilesTwigExtension $entryFilesTwigExtension,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('encore_entry_script_tags_application', $this->renderApplicationScriptTags(...), ['is_safe' => ['html']]),
            new TwigFunction('encore_entry_link_tags_application', $this->renderApplicationLinkTags(...), ['is_safe' => ['html']]),
            new TwigFunction('encore_entry_script_tags_domain', $this->renderDomainScriptTags(...), ['is_safe' => ['html']]),
            new TwigFunction('encore_entry_link_tags_domain', $this->renderDomainLinkTags(...), ['is_safe' => ['html']]),
            new TwigFunction('encore_entry_script_tags_project', $this->renderProjectScriptTags(...), ['is_safe' => ['html']]),
            new TwigFunction('encore_entry_link_tags_project', $this->renderProjectLinkTags(...), ['is_safe' => ['html']]),
            new TwigFunction('encore_entry_script_tags_module', $this->renderModuleScriptTags(...), ['is_safe' => ['html']]),
            new TwigFunction('encore_entry_link_tags_module', $this->renderModuleLinkTags(...), ['is_safe' => ['html']]),
        ];
    }

    public function renderApplicationScriptTags(?Application $application): string
    {
        return $this->renderScriptTags($this->applicationEntryName($application));
    }

    public function renderApplicationLinkTags(?Application $application): string
    {
        return $this->renderLinkTags($this->applicationEntryName($application));
    }

    public function renderDomainScriptTags(?Domain $domain): string
    {
        return $this->renderScriptTags($this->domainEntryName($domain));
    }

    public function renderDomainLinkTags(?Domain $domain): string
    {
        return $this->renderLinkTags($this->domainEntryName($domain));
    }

    public function renderProjectScriptTags(?Project $project): string
    {
        return $this->renderScriptTags($this->projectEntryName($project));
    }

    public function renderProjectLinkTags(?Project $project): string
    {
        return $this->renderLinkTags($this->projectEntryName($project));
    }

    public function renderModuleScriptTags(?Module $module): string
    {
        return $this->renderScriptTags($this->moduleEntryName($module));
    }

    public function renderModuleLinkTags(?Module $module): string
    {
        return $this->renderLinkTags($this->moduleEntryName($module));
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
        $id = trim((string) $domain?->getId(true));
        if ($id === '') {
            return null;
        }

        $entryName = 'domains/'.$id;
        if ($this->entryFilesTwigExtension->entryExists($entryName)) {
            return $entryName;
        }

        if (strpos($id, '/') !== false) {
            return $entryName;
        }

        // Do not attempt to discover domain entries from installed packages.
        // Domains are created only via bootstrap; fall back to the default entry name.
        return $entryName;
    }

    private function applicationEntryName(?Application $application): ?string
    {
        $id = trim((string) $application?->getId(true));
        if ($id === '') {
            return null;
        }

        $entryName = 'applications/'.$id;
        if ($this->entryFilesTwigExtension->entryExists($entryName)) {
            return $entryName;
        }

        if (strpos($id, '/') !== false) {
            return $entryName;
        }

        return $entryName;
    }

    private function projectEntryName(?Project $project): ?string
    {
        $id = trim((string) $project?->getId(true));
        if ($id === '') {
            return null;
        }

        $entryName = 'projects/'.$id;
        if ($this->entryFilesTwigExtension->entryExists($entryName)) {
            return $entryName;
        }

        if (strpos($id, '/') !== false) {
            return $entryName;
        }

        return $entryName;
    }

    private function moduleEntryName(?Module $module): ?string
    {
        $id = trim((string) $module?->getId(true));
        if ($id === '') {
            return null;
        }

        $entryName = 'modules/'.$id;
        if ($this->entryFilesTwigExtension->entryExists($entryName)) {
            return $entryName;
        }

        if (strpos($id, '/') !== false) {
            return $entryName;
        }

        return $entryName;
    }
}
