<?php

namespace Symfonicat\Service;

use Symfonicat\Entity\Application;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Electron;
use Symfonicat\Entity\Project;
use Symfonicat\Repository\ElectronRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ElectronService
{
    public function __construct(
        private readonly ApplicationService $applicationService,
        private readonly DomainService $domainService,
        private readonly ElectronRepository $electronRepository,
        private readonly ProjectService $projectService,
        private readonly RequestStack $requestStack,
        private readonly RuntimeConfig $runtimeConfig,
    ) {
    }

    public function load(): ?Electron
    {
        $request = $this->requestStack->getCurrentRequest();
        $electronId = $this->electronIdFromRequest($request);
        if ($electronId !== null) {
            return $this->runtimeConfig->electronById($electronId);
        }

        $application = $request?->attributes->get('application');
        $project = $request?->attributes->get('project');
        $domain = $request?->attributes->get('domain');

        return $this->loadForContext(
            $application instanceof Application ? $application : null,
            $domain instanceof Domain ? $domain : null,
            $project instanceof Project ? $project : null,
        );
    }

    public function loadForContext(?Application $application, ?Domain $domain, ?Project $project): ?Electron
    {
        if (!$this->isElectronRequest()) {
            return null;
        }

        if (!$application instanceof Application) {
            $application = $this->applicationService->load();
        }

        if ($application instanceof Application) {
            if ($this->usesDatabaseRuntime()) {
                return $this->electronRepository->findOneForApplication($application);
            }

            return $this->runtimeConfig->electronForApplication($application);
        }

        if (!$project instanceof Project) {
            $project = $this->projectService->load();
        }

        if (!$domain instanceof Domain) {
            $domain = $this->domainService->load();
        }

        if ($project instanceof Project) {
            if ($this->usesDatabaseRuntime()) {
                if ($domain instanceof Domain) {
                    return $this->electronRepository->findOneForProjectAndDomain($project, $domain)
                        ?? $this->electronRepository->findOneForProject($project);
                }

                return $this->electronRepository->findOneForProject($project);
            }

            if ($domain instanceof Domain) {
                return $this->runtimeConfig->electronForProject($project, $domain)
                    ?? $this->runtimeConfig->electronForProject($project);
            }

            return $this->runtimeConfig->electronForProject($project);
        }

        if ($domain instanceof Domain) {
            if ($this->usesDatabaseRuntime()) {
                return $this->electronRepository->findOneForDomain($domain);
            }

            return $this->runtimeConfig->electronForDomain($domain);
        }

        return null;
    }

    public function isElectronRequest(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return false;
        }

        if ($request->query->has('electron')) {
            $raw = strtolower(trim((string) $request->query->get('electron', '')));

            return !in_array($raw, ['0', 'false', 'no', 'off'], true);
        }

        if ($request->hasSession()) {
            return (bool) $request->getSession()->get('is_electron_app', false)
                || trim((string) $request->getSession()->get('symfonicat_electron_id', '')) !== '';
        }

        return false;
    }

    private function electronIdFromRequest(?Request $request): ?string
    {
        if (!$request instanceof Request) {
            return null;
        }

        if ($request->query->has('electron')) {
            $raw = trim((string) $request->query->get('electron', ''));
            if ($raw !== '' && !in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true)) {
                if ($request->hasSession()) {
                    $request->getSession()->set('is_electron_app', true);
                    $request->getSession()->set('symfonicat_electron_id', $raw);
                }

                return $raw;
            }
        }

        if ($request->hasSession()) {
            $sessionId = trim((string) $request->getSession()->get('symfonicat_electron_id', ''));

            return $sessionId === '' ? null : $sessionId;
        }

        return null;
    }

    private function usesDatabaseRuntime(): bool
    {
        return ($_SERVER['APP_ENV'] ?? null) === 'test';
    }
}
