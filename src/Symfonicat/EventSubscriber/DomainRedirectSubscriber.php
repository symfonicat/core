<?php

namespace Symfonicat\EventSubscriber;

use Symfonicat\Service\DomainService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class DomainRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly DomainService $domainService)
    {
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

        $domain = $this->domainService->load();
        if ($domain === null) {
            return;
        }

        $redirectDomain = $domain->getRedirectDomain();
        if ($redirectDomain === null) {
            return;
        }

        $targetId = $redirectDomain->getId();
        if ($targetId === null || $targetId === '') {
            return;
        }

        if ($domain->getId() === $targetId) {
            return;
        }

        $scheme = $event->getRequest()->getScheme();
        $target = $scheme.'://'.$targetId;
        $event->setResponse(new RedirectResponse($target, 301));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 4096],
        ];
    }
}
