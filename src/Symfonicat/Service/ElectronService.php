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
    ) {
    }

    public function load(): ?Electron
    {
        $request = $this->requestStack->getCurrentRequest();
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
            return $this->electronRepository->findOneForApplication($application);
        }

        if (!$project instanceof Project) {
            $project = $this->projectService->load();
        }

        if (!$domain instanceof Domain) {
            $domain = $this->domainService->load();
        }

        if ($project instanceof Project) {
            if ($domain instanceof Domain) {
                return $this->electronRepository->findOneForProjectAndDomain($project, $domain)
                    ?? $this->electronRepository->findOneForProject($project);
            }

            return $this->electronRepository->findOneForProject($project);
        }

        if ($domain instanceof Domain) {
            return $this->electronRepository->findOneForDomain($domain);
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
            return (bool) $request->getSession()->get('is_electron_app', false);
        }

        return false;
    }
}
