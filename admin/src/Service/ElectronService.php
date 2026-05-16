<?php

namespace Symfonicat\Service;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Electron;
use Symfonicat\Entity\Subdomain;
use Symfonicat\Repository\ElectronRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ElectronService
{
    public function __construct(
        private readonly DomainService $domainService,
        private readonly ElectronRepository $electronRepository,
        private readonly SubdomainService $subdomainService,
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

        $subdomain = $request?->attributes->get('subdomain');
        $domain = $request?->attributes->get('domain');

        return $this->loadForContext(
            $domain instanceof Domain ? $domain : null,
            $subdomain instanceof Subdomain ? $subdomain : null,
        );
    }

    public function loadForContext(?Domain $domain, ?Subdomain $subdomain): ?Electron
    {
        if (!$this->isElectronRequest()) {
            return null;
        }

        if (!$subdomain instanceof Subdomain) {
            $subdomain = $this->subdomainService->load();
        }

        if (!$domain instanceof Domain) {
            $domain = $this->domainService->load();
        }

        if ($subdomain instanceof Subdomain) {
            if ($this->usesDatabaseRuntime()) {
                if ($domain instanceof Domain) {
                    return $this->electronRepository->findOneForSubdomainAndDomain($subdomain, $domain)
                        ?? $this->electronRepository->findOneForSubdomain($subdomain);
                }

                return $this->electronRepository->findOneForSubdomain($subdomain);
            }

            if ($domain instanceof Domain) {
                return $this->runtimeConfig->electronForSubdomain($subdomain, $domain)
                    ?? $this->runtimeConfig->electronForSubdomain($subdomain);
            }

            return $this->runtimeConfig->electronForSubdomain($subdomain);
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
