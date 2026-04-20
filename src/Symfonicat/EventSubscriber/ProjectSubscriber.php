<?php

namespace Symfonicat\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\SubdomainService;
use Symfonicat\Service\ProjectService;

class ProjectSubscriber implements EventSubscriberInterface
{

    public function __construct (

        public DomainService $domainService,
        public SubdomainService $subdomainService,
        public ProjectService $projectService,

    ) {}

    public function onKernelRequest (RequestEvent $event) : void
    {
        if ($event->hasResponse()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (str_starts_with($path, '/admin')) {
            return;
        }

        $raw = $this->subdomainService->getSubdomainsRaw();
        $arr = $this->subdomainService->getSubdomains();

        if (
            
            isset($raw[0]) &&
            $raw[0] === 'www'

        ) {

            $scheme = $event->getRequest()->getScheme();
            $host = $this->domainService->host();

            // Rebuild the host from whatever remained after stripping `www`.
            // When `www` was the only subdomain segment, `$arr` is empty and
            // prepending `"." . $host` would produce `http://.example.com`, so
            // we fall through to the bare host.
            $remainder = implode('.', array_reverse(array_values($arr)));
            $target = $remainder === ''
                ? "$scheme://$host"
                : "$scheme://$remainder.$host";
            $target = $this->withPort($event->getRequest(), $target);

            $event->setResponse(new RedirectResponse($target, 301));
            return;
        }

        if ( count($arr) > 1) {

            $scheme = $event->getRequest()->getScheme();
            $host = $this->domainService->host();

            $target = "$scheme://$arr[0].$host";
            $target = $this->withPort($event->getRequest(), $target);

            $event->setResponse(new RedirectResponse($target, 301));
            return;
        }

        $request = $event->getRequest();
        $project = $this->projectService->load();

        if (
        
            isset($arr[0]) &&
            !$project

        ) {

            $scheme = $event->getRequest()->getScheme();
            $host = $this->domainService->host();

            $target = "$scheme://$host";
            $target = $this->withPort($event->getRequest(), $target);

            $event->setResponse(new RedirectResponse($target, 301));
            return;
        }

        $request->attributes->set('project', $project);

        if (!$request->attributes->has('symfonicat_use_project_catch_all')) {
            $request->attributes->set('symfonicat_use_project_catch_all', $project !== null);
        }

    }

    private function withPort (
    
        Request $request,
        string $host

    ) : string {
        
        $port = $request->getPort();
        $defaultPort = $request->isSecure() ? 443 : 80;

        if (NULL === $port || $port === $defaultPort) {
            return $host;
        }
        
        return $host.':'.$port;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1024],
        ];
    }
}
