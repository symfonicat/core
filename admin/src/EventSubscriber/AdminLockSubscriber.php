<?php

namespace Symfonicat\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final class AdminLockSubscriber implements EventSubscriberInterface
{
    private readonly string $lockPath;

    public function __construct(string $subdomainDir)
    {
        $this->lockPath = rtrim($subdomainDir, '/\\').\DIRECTORY_SEPARATOR.'symfonicat.lock';
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $adminPath = str_starts_with($request->getPathInfo(), '/admin');
        $caddyLockedRequest = $request->headers->get('X-Symfonicat-Admin-Locked') === '1';

        if (!$event->isMainRequest() && $request->attributes->has('exception')) {
            return;
        }

        if (!$adminPath && !$caddyLockedRequest) {
            return;
        }

        if (is_file($this->lockPath)) {
            return;
        }

        throw new NotFoundHttpException(
            'Not Found',
            null,
            0,
            [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'X-Robots-Tag' => 'noindex, nofollow',
            ],
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 4096],
        ];
    }
}
