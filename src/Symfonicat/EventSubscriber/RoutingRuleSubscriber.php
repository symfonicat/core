<?php

namespace Symfonicat\EventSubscriber;

use Symfonicat\Controller\MainController;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\PathService;
use Symfonicat\Service\ProjectService;
use Symfonicat\Service\RoutingRuleService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class RoutingRuleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RoutingRuleService $routingRuleService,
        private readonly DomainService $domainService,
        private readonly ProjectService $projectService,
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

        $argument = $this->pathService->arg(0);
        if ($argument === null || $argument === '') {
            return;
        }

        $request = $event->getRequest();
        $domain = $this->domainService->load();

        if ($domain !== null && $this->routingRuleService->getTypeDomainByDomainAndArgument($domain, $argument) !== null) {
            $request->attributes->set('_controller', MainController::class.'::main');
            $request->attributes->set('_route', 'symfonicat_routing_rule_domain');
            $request->attributes->set('_route_params', [
                'path' => trim($request->getPathInfo(), '/'),
            ]);
            $request->attributes->set('path', trim($request->getPathInfo(), '/'));
            $request->attributes->set('symfonicat_force_domain_main', true);

            return;
        }

        $project = $this->projectService->load();
        if ($project !== null && $this->routingRuleService->getTypeProjectByProjectAndArgument($project, $argument) !== null) {
            $request->attributes->set('symfonicat_use_project_catch_all', false);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 2048],
        ];
    }
}
