<?php

namespace Symfonicat\EventSubscriber;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Endpoint;
use Symfonicat\Entity\Subdomain;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\ModuleRequestContextStore;
use Symfonicat\Service\PathService;
use Symfonicat\Service\RuntimeConfig;
use Symfonicat\Service\RuntimeRenderer;
use Symfonicat\Service\SubdomainService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class PublicRuntimeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly DomainService $domainService,
        private readonly SubdomainService $subdomainService,
        private readonly PathService $pathService,
        private readonly RuntimeConfig $runtimeConfig,
        private readonly ModuleRequestContextStore $moduleRequestContextStore,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $event->hasResponse()) {
            return;
        }

        $request = $event->getRequest();
        if ($this->isBlockedPath($request)) {
            return;
        }

        $domain = $this->domainService->load();
        if ($domain instanceof Domain) {
            $request->attributes->set('domain', $domain);
        }

        $subdomain = $this->subdomainService->load();
        if ($subdomain instanceof Subdomain) {
            $request->attributes->set('subdomain', $subdomain);
        }

        if ($this->isModulePath($request)) {
            $moduleContext = $this->moduleRequestContextStore->resolve($request);
            if ($moduleContext === null) {
                return;
            }

            $request->attributes->set('symfonicat_module_request_valid', true);

            $endpointId = trim((string) ($moduleContext['endpoint_id'] ?? ''));
            if ($endpointId !== '') {
                $endpoint = $this->endpointById($endpointId);
                if ($endpoint instanceof Endpoint) {
                    $request->attributes->set('endpoint', $endpoint);
                }
            }

            return;
        }

        $endpoint = $this->matchEndpoint();
        if ($endpoint instanceof Endpoint) {
            $request->attributes->set('endpoint', $endpoint);
            $this->allowRuntimeRoute($request, RuntimeRenderer::TARGET_ENDPOINT);

            return;
        }

        if ($subdomain instanceof Subdomain) {
            $this->allowRuntimeRoute($request, RuntimeRenderer::TARGET_SUBDOMAIN);

            return;
        }

        if ($domain instanceof Domain) {
            $this->allowRuntimeRoute($request, RuntimeRenderer::TARGET_DOMAIN);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512],
        ];
    }

    private function matchEndpoint(): ?Endpoint
    {
        $path = $this->pathService->path();
        $endpoints = $this->runtimeConfig->endpoints();

        usort($endpoints, static fn (Endpoint $left, Endpoint $right): int => count($right->getArguments()) <=> count($left->getArguments()));

        $domain = $this->domainService->load();
        $subdomain = $this->subdomainService->load();

        foreach ($endpoints as $endpoint) {
            if (!$this->pathService->matchesArguments($endpoint->getArguments(), $path, $endpoint->isCatch())) {
                continue;
            }

            $enforcement = $endpoint->getEnforce();

            if ($enforcement === Endpoint::ENFORCE_DOMAIN) {
                // require a domain and no subdomain, and domain must match
                if (!($domain instanceof Domain) || $subdomain instanceof Subdomain) {
                    continue;
                }

                $targetDomain = $endpoint->getDomain();
                if (!($targetDomain instanceof Domain) || $targetDomain->getTld() !== $domain->getTld()) {
                    continue;
                }
            } elseif ($enforcement === Endpoint::ENFORCE_SUBDOMAIN) {
                // require a subdomain match; if a target domain is set, it must also match
                if (!($subdomain instanceof Subdomain)) {
                    continue;
                }

                $targetSubdomain = $endpoint->getSubdomain();
                if (!$this->sameSubdomain($targetSubdomain, $subdomain)) {
                    continue;
                }
                $targetDomain = $endpoint->getDomain();
                if ($targetDomain instanceof Domain && $targetDomain->getTld() !== $domain?->getTld()) {
                    continue;
                }
            } elseif ($enforcement !== null) {
                continue;
            }

            return $endpoint;
        }

        return null;
    }

    private function allowRuntimeRoute(Request $request, string $target): void
    {
        $request->attributes->set('symfonicat_runtime_target', $target);
        $request->attributes->set('symfonicat_runtime_route_allowed', true);
    }

    private function endpointById(string $endpointId): ?Endpoint
    {
        return $this->runtimeConfig->endpointById($endpointId);
    }

    private function isBlockedPath(Request $request): bool
    {
        $path = $request->getPathInfo();

        return $path === '/core'
            || str_starts_with($path, '/core/')
            || $path === '/application'
            || str_starts_with($path, '/application/');
    }

    private function isModulePath(Request $request): bool
    {
        $path = $request->getPathInfo();

        return $path === '/m' || str_starts_with($path, '/m/');
    }

    private function sameSubdomain(?Subdomain $left, ?Subdomain $right): bool
    {
        if (!$left instanceof Subdomain || !$right instanceof Subdomain) {
            return false;
        }

        if ($left === $right) {
            return true;
        }

        $leftId = $left->getId();
        $rightId = $right->getId();
        if ($leftId !== null && $rightId !== null && $leftId === $rightId) {
            return true;
        }

        $leftAffix = trim((string) $left->getAffix());
        $rightAffix = trim((string) $right->getAffix());
        if ($leftAffix === '' || $rightAffix === '' || $leftAffix !== $rightAffix) {
            return false;
        }

        return $left->getDomain()?->getTld() === $right->getDomain()?->getTld();
    }
}
