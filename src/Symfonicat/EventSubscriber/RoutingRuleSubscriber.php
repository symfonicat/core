<?php

namespace Symfonicat\EventSubscriber;

use Symfonicat\Service\PathService;
use Symfonicat\Service\RoutingRuleService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class RoutingRuleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RoutingRuleService $routingRuleService,
        private readonly PathService $pathService,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($event->hasResponse()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (str_starts_with($path, '/admin')) {
            return;
        }

        $slug = $this->pathService->arg(0);
        if ($slug === null || $slug === '') {
            return;
        }

        $request = $event->getRequest();

        foreach ($this->routingRuleService->loadDomainRules() as $rule) {
            if ($rule->getSlug() !== $slug) {
                continue;
            }

            $targetDomainId = $rule->getDomain()?->getId();
            if ($targetDomainId === null) {
                continue;
            }

            if (strcasecmp($targetDomainId, $request->getHost()) === 0) {
                continue;
            }

            $target = $this->buildTarget($request, $targetDomainId);
            $event->setResponse(new RedirectResponse($target, 301));

            return;
        }
    }

    private function buildTarget(Request $request, string $host): string
    {
        $scheme = $request->getScheme();
        $target = $scheme.'://'.$host.$request->getRequestUri();
        $port = $request->getPort();
        $defaultPort = $request->isSecure() ? 443 : 80;

        if ($port === null || $port === $defaultPort) {
            return $target;
        }

        return $scheme.'://'.$host.':'.$port.$request->getRequestUri();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 2048],
        ];
    }
}
