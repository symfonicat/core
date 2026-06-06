<?php

namespace Symfonicat\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SymfonicatModuleSubscriber implements EventSubscriberInterface
{
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $event->hasResponse()) {
            return;
        }

        $request = $event->getRequest();
        if (!$this->isModulePath($request)) {
            return;
        }

        $request->attributes->set('module_json', $this->decodePayload($request->getContent()));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 256],
        ];
    }

    private function isModulePath(Request $request): bool
    {
        $path = $request->getPathInfo();

        return $path === '/m' || str_starts_with($path, '/m/');
    }

    /**
     * @return mixed
     */
    private function decodePayload(string $payload)
    {
        return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    }
}
