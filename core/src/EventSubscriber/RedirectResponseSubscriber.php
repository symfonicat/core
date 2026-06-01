<?php

namespace Symfonicat\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class RedirectResponseSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        if (!$response->isRedirection()) {
            return;
        }

        $request = $event->getRequest();
        $location = $response->headers->get('Location', '');

        $this->logger->info('Redirect response emitted.', [
            'status' => $response->getStatusCode(),
            'location' => $location,
            'request' => [
                'method' => $request->getMethod(),
                'scheme' => $request->getScheme(),
                'host' => $request->getHttpHost(),
                'uri' => $request->getUri(),
                'path' => $request->getPathInfo(),
                'query' => $request->getQueryString(),
                'route' => $request->attributes->get('_route'),
                'controller' => $request->attributes->get('_controller'),
                'x_forwarded_proto' => $request->headers->get('X-Forwarded-Proto'),
                'x_forwarded_host' => $request->headers->get('X-Forwarded-Host'),
                'forwarded' => $request->headers->get('Forwarded'),
            ],
        ]);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -256],
        ];
    }
}
