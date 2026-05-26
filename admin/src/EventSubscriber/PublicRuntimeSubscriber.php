<?php

namespace Symfonicat\EventSubscriber;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Endpoint;
use Symfonicat\Entity\Subdomain;
use Symfonicat\Repository\EndpointRepository;
use Symfonicat\Service\DomainService;
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
        private readonly EndpointRepository $endpointRepository,
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
            $endpoint = $this->endpointFromHeader($request);
            if ($endpoint instanceof Endpoint) {
                $request->attributes->set('endpoint', $endpoint);
            }

            return;
        }

        $endpoint = $this->matchEndpoint();
        if ($endpoint instanceof Endpoint) {
            $request->attributes->set('endpoint', $endpoint);
            $this->allowRuntimeRoute($request, RuntimeRenderer::TARGET_ENDPOINT);

            return;
        }

        if ($subdomain instanceof Subdomain && $this->isRootOrCatch($request, $subdomain->isCatch())) {
            $this->allowRuntimeRoute($request, RuntimeRenderer::TARGET_SUBDOMAIN);

            return;
        }

        if (!$subdomain instanceof Subdomain && $domain instanceof Domain && $this->isRootOrCatch($request, $domain->isCatch())) {
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
        $endpoints = $this->usesDatabaseRuntime()
            ? $this->endpointRepository->findAllOrderedById()
            : $this->runtimeConfig->endpoints();

        usort($endpoints, static fn (Endpoint $left, Endpoint $right): int => count($right->getArguments()) <=> count($left->getArguments()));

        foreach ($endpoints as $endpoint) {
            if ($this->pathService->matchesArguments($endpoint->getArguments(), $path, $endpoint->isCatch())) {
                return $endpoint;
            }
        }

        return null;
    }

    private function allowRuntimeRoute(Request $request, string $target): void
    {
        $request->attributes->set('symfonicat_runtime_target', $target);
        $request->attributes->set('symfonicat_runtime_route_allowed', true);
    }

    private function isRootOrCatch(Request $request, bool $catch): bool
    {
        return $request->getPathInfo() === '/' || $catch;
    }

    private function endpointFromHeader(Request $request): ?Endpoint
    {
        $endpointId = trim((string) $request->headers->get('X-Symfonicat-Endpoint', ''));
        if ($endpointId === '') {
            return null;
        }

        if ($this->usesDatabaseRuntime()) {
            $endpoint = $this->endpointRepository->find($endpointId);

            return $endpoint instanceof Endpoint ? $endpoint : null;
        }

        return $this->runtimeConfig->endpointById($endpointId);
    }

    private function isBlockedPath(Request $request): bool
    {
        $path = $request->getPathInfo();

        return $path === '/admin'
            || str_starts_with($path, '/admin/')
            || $path === '/application'
            || str_starts_with($path, '/application/');
    }

    private function isModulePath(Request $request): bool
    {
        $path = $request->getPathInfo();

        return $path === '/m' || str_starts_with($path, '/m/');
    }

    private function usesDatabaseRuntime(): bool
    {
        return ($_SERVER['APP_ENV'] ?? null) === 'test';
    }
}
