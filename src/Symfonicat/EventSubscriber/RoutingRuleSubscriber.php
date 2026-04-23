<?php

namespace Symfonicat\EventSubscriber;

use Symfonicat\Controller\MainController;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Project;
use Symfonicat\Entity\RoutingRule;
use Symfonicat\Service\ApplicationService;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\PathService;
use Symfonicat\Service\ProjectService;
use Symfonicat\Service\RoutingRuleService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Error\LoaderError;

final class RoutingRuleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RoutingRuleService $routingRuleService,
        private readonly ApplicationService $applicationService,
        private readonly DomainService $domainService,
        private readonly ProjectService $projectService,
        private readonly PathService $pathService,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly HttpKernelInterface $httpKernel,
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

        $request = $event->getRequest();
        if ($request->attributes->getBoolean('symfonicat_routing_rule_active')) {
            return;
        }

        $path = $request->getPathInfo();
        if (str_starts_with($path, '/admin')) {
            return;
        }

        $isModulePath = $path === '/m' || str_starts_with($path, '/m/');

        $domain = $this->domainService->load();
        $project = $this->projectService->load();
        $path = $this->pathService->path();

        if ($domain !== null) {
            $redirectRule = $this->routingRuleService->getRedirectRuleForDomain($domain);
            if ($redirectRule !== null) {
                $response = $this->buildRedirectResponse($request, $redirectRule, $domain);
                if ($response !== null) {
                    $event->setResponse($response);

                    return;
                }
            }
        }

        if ($project !== null) {
            $redirectRule = $this->routingRuleService->getRedirectRuleForProject($project);
            if ($redirectRule !== null) {
                $response = $this->buildRedirectResponse($request, $redirectRule, $domain);
                if ($response !== null) {
                    $event->setResponse($response);

                    return;
                }
            }
        }

        if ($request->getPathInfo() === '/' && $project === null && $domain !== null) {
            $routeRule = $this->routingRuleService->getRouteRuleForDomain($domain);
            if ($routeRule !== null) {
                $event->setResponse($this->renderRoute($request, $routeRule));

                return;
            }
        }

        if ($request->getPathInfo() === '/' && $project !== null) {
            $routeRule = $this->routingRuleService->getRouteRuleForProject($project);
            if ($routeRule !== null) {
                $event->setResponse($this->renderRoute($request, $routeRule));

                return;
            }
        }

        if ($isModulePath) {
            return;
        }

        $application = $this->applicationService->load();
        if ($application !== null) {
            $request->attributes->set('application', $application);
            $request->attributes->set('symfonicat_routing_rule_active', true);
            $event->setResponse($this->renderApplication($application));

            return;
        }

        if ($domain !== null && $this->routingRuleService->getTypeDomainByDomainAndPath($domain, $path) !== null) {
            $request->attributes->set('_controller', MainController::class.'::main');
            $request->attributes->set('_route', 'symfonicat_routing_rule_domain');
            $request->attributes->set('_route_params', [
                'path' => trim($request->getPathInfo(), '/'),
            ]);
            $request->attributes->set('path', trim($request->getPathInfo(), '/'));
            $request->attributes->set('symfonicat_force_domain_main', true);

            return;
        }

        if ($project !== null && $this->routingRuleService->getTypeProjectByProjectAndPath($project, $path) !== null) {
            $request->attributes->set('symfonicat_use_project_catch_all', false);

            return;
        }
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->get('application') instanceof Application) {
            return;
        }

        if (str_starts_with($request->getPathInfo(), '/admin')) {
            return;
        }

        $application = $this->applicationService->loadFromRoute((string) $request->attributes->get('_route', ''));
        if ($application instanceof Application) {
            $request->attributes->set('application', $application);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 2048],
            KernelEvents::CONTROLLER => ['onKernelController', 0],
        ];
    }

    private function buildRedirectResponse(Request $request, RoutingRule $rule, ?Domain $currentDomain): ?Response
    {
        $host = null;

        if ($rule->isDomainRedirectTarget()) {
            $host = $rule->getRedirectDomain()?->getId();
        } elseif ($rule->isProjectRedirectTarget()) {
            $host = $this->resolveProjectRedirectHost($request, $rule->getRedirectProject(), $currentDomain);
        }

        $host = trim((string) $host);
        if ($host === '') {
            return null;
        }

        $target = sprintf('%s://%s%s', $request->getScheme(), $host, $request->getRequestUri());
        $target = $this->withPort($request, $target);

        if ($target === $request->getUri()) {
            return null;
        }

        return new RedirectResponse($target, 302);
    }

    private function resolveProjectRedirectHost(Request $request, ?Project $project, ?Domain $currentDomain): ?string
    {
        if ($project === null) {
            return null;
        }

        $projectId = trim((string) $project->getId());
        if ($projectId === '') {
            return null;
        }

        $domainId = null;

        if ($currentDomain !== null && $project->hasDomain($currentDomain)) {
            $domainId = $currentDomain->getId();
        } else {
            foreach ($project->getDomains() as $domain) {
                $candidateId = trim((string) $domain->getId());
                if ($candidateId !== '') {
                    $domainId = $candidateId;

                    break;
                }
            }
        }

        if ($domainId === null) {
            $domainId = $this->domainService->host();
        }

        $domainId = trim((string) $domainId);
        if ($domainId === '') {
            return null;
        }

        return sprintf('%s.%s', $projectId, $domainId);
    }

    private function renderRoute(Request $request, RoutingRule $rule): Response
    {
        $routeName = trim((string) $rule->getRoute());
        if ($routeName === '') {
            throw new NotFoundHttpException('Routing rule route is empty.');
        }

        try {
            $uri = $this->urlGenerator->generate($routeName);
        } catch (RouteNotFoundException|MissingMandatoryParametersException) {
            throw new NotFoundHttpException(sprintf('Routing rule route "%s" was not found.', $routeName));
        }

        $overrideRequest = Request::create(
            $uri,
            Request::METHOD_GET,
            $request->query->all(),
            $request->cookies->all(),
            [],
            $request->server->all(),
        );

        if ($request->hasSession()) {
            $overrideRequest->setSession($request->getSession());
        }

        $overrideRequest->attributes->set('symfonicat_routing_rule_active', true);
        $overrideRequest->attributes->set('symfonicat_route_override_active', true);

        return $this->httpKernel->handle($overrideRequest, HttpKernelInterface::SUB_REQUEST);
    }

    private function renderApplication(Application $application): Response
    {
        $template = $this->resolveApplicationTemplate(
            sprintf('application/overrides/%s.html.twig', $application->getId()),
            'application/main.html.twig',
        );

        return new Response($this->twig->render($template));
    }

    private function resolveApplicationTemplate(string $overrideTemplate, string $fallbackTemplate): string
    {
        try {
            $this->twig->load($overrideTemplate);

            return $overrideTemplate;
        } catch (LoaderError) {
            return $fallbackTemplate;
        }
    }

    private function withPort(Request $request, string $url): string
    {
        $port = $request->getPort();
        $defaultPort = $request->isSecure() ? 443 : 80;

        if ($port === null || $port === $defaultPort) {
            return $url;
        }

        return preg_replace('#^(https?://[^/]+)#', '$1:'.$port, $url, 1) ?? $url;
    }
}
