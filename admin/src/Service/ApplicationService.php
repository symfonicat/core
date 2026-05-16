<?php

namespace Symfonicat\Service;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\Subdomain;
use Symfonicat\Repository\ApplicationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ApplicationService
{
    public function __construct(
        private readonly DomainService $domainService,
        private readonly ApplicationRepository $applicationRepository,
        private readonly SubdomainService $subdomainService,
        private readonly RequestStack $requestStack,
        private readonly RuntimeConfig $runtimeConfig,
    ) {
    }

    public function load(): ?Application
    {
        $request = $this->requestStack->getCurrentRequest();
        $applicationId = $this->applicationIdFromRequest($request);
        if ($applicationId !== null) {
            return $this->runtimeConfig->applicationById($applicationId);
        }

        $subdomain = $request?->attributes->get('subdomain');
        $domain = $request?->attributes->get('domain');

        return $this->loadForContext(
            $domain instanceof Domain ? $domain : null,
            $subdomain instanceof Subdomain ? $subdomain : null,
        );
    }

    public function loadForContext(?Domain $domain, ?Subdomain $subdomain): ?Application
    {
        if (!$this->isApplicationRequest()) {
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
                    return $this->applicationRepository->findOneForSubdomainAndDomain($subdomain, $domain)
                        ?? $this->applicationRepository->findOneForSubdomain($subdomain);
                }

                return $this->applicationRepository->findOneForSubdomain($subdomain);
            }

            if ($domain instanceof Domain) {
                return $this->runtimeConfig->applicationForSubdomain($subdomain, $domain)
                    ?? $this->runtimeConfig->applicationForSubdomain($subdomain);
            }

            return $this->runtimeConfig->applicationForSubdomain($subdomain);
        }

        if ($domain instanceof Domain) {
            if ($this->usesDatabaseRuntime()) {
                return $this->applicationRepository->findOneForDomain($domain);
            }

            return $this->runtimeConfig->applicationForDomain($domain);
        }

        return null;
    }

    public function isApplicationRequest(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return false;
        }

        if ($request->query->has('application')) {
            $raw = strtolower(trim((string) $request->query->get('application', '')));

            return !in_array($raw, ['0', 'false', 'no', 'off'], true);
        }

        if ($request->hasSession()) {
            return (bool) $request->getSession()->get('is_application_app', false)
                || trim((string) $request->getSession()->get('symfonicat_application_id', '')) !== '';
        }

        return false;
    }

    private function applicationIdFromRequest(?Request $request): ?string
    {
        if (!$request instanceof Request) {
            return null;
        }

        if ($request->query->has('application')) {
            $raw = trim((string) $request->query->get('application', ''));
            if ($raw !== '' && !in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true)) {
                if ($request->hasSession()) {
                    $request->getSession()->set('is_application_app', true);
                    $request->getSession()->set('symfonicat_application_id', $raw);
                }

                return $raw;
            }
        }

        if ($request->hasSession()) {
            $sessionId = trim((string) $request->getSession()->get('symfonicat_application_id', ''));

            return $sessionId === '' ? null : $sessionId;
        }

        return null;
    }

    private function usesDatabaseRuntime(): bool
    {
        return ($_SERVER['APP_ENV'] ?? null) === 'test';
    }
}
