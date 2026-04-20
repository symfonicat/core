<?php

namespace Symfonicat\Controller;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Project;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\ProjectService;
use Symfonicat\Service\SubdomainService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Error\LoaderError;
use Twig\Environment;

final class MainController extends AbstractController
{
    private ?Domain $domain;
    private ?Project $project;

    public function __construct(
        private readonly DomainService $domainService,
        private readonly ProjectService $projectService,
        private readonly SubdomainService $subdomainService,
        private readonly Environment $twig,
        private readonly HttpKernelInterface $httpKernel,
    ) {
        $this->domain = $this->domainService->load();
        $this->project = $this->projectService->load();
    }

    #[Route('/', name: 'app_project_root', methods: ['GET'], defaults: ['path' => ''], condition: 'not request.attributes.get("project")')]
    #[Route('/{path}', name: 'app_project', methods: ['GET'], requirements: ['path' => '(?!m(?:/|$)).*'], defaults: ['path' => ''], priority: -1000, condition: 'request.attributes.get("project") and request.attributes.get("symfonicat_use_project_catch_all", true)')]
    public function main(Request $request, string $path = ''): Response
    {
        if ($response = $this->renderOverride($request)) {
            return $response;
        }

        if ($request->attributes->getBoolean('symfonicat_force_domain_main')) {
            return $this->renderDomain();
        }

        if (!$this->subdomainService->getSubdomains()) {
            return $this->renderDomain();
        }

        return $this->renderProject();
    }

    private function renderDomain(): Response
    {
        if (!$this->domain) {
            throw $this->createNotFoundException();
        }

        $template = $this->resolveTemplate(
            sprintf('domain/overrides/%s.html.twig', $this->domain->getId()),
            'domain/main.html.twig',
        );

        return $this->render($template);
    }

    private function renderProject(): Response
    {
        if (!$this->project) {
            throw $this->createNotFoundException();
        }

        $template = 'project/main.html.twig';
        $projectId = $this->project->getId();

        if (is_string($projectId) && $projectId !== '') {
            $template = $this->resolveTemplate(
                sprintf('project/overrides/%s.html.twig', $projectId),
                $template,
            );
        }

        return $this->render($template);
    }

    private function renderOverride(Request $request): ?Response
    {
        if ($request->attributes->getBoolean('symfonicat_route_override_active')) {
            return NULL;
        }

        $entity = NULL;

        if ($this->project && $this->project->getRouteOverride()) {
            $entity = $this->project;
        } elseif ($this->domain && $this->domain->getRouteOverride()) {
            $entity = $this->domain;
        }

        if ($entity === NULL) {
            return NULL;
        }

        $routeName = trim((string) $entity->getRouteName());
        if ($routeName === '') {
            return NULL;
        }

        try {
            $uri = $this->generateUrl($routeName);
        } catch (RouteNotFoundException|MissingMandatoryParametersException) {
            throw $this->createNotFoundException(sprintf('Route override "%s" was not found.', $routeName));
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

        $overrideRequest->attributes->set('symfonicat_route_override_active', true);

        return $this->httpKernel->handle($overrideRequest, HttpKernelInterface::SUB_REQUEST);
    }

    private function resolveTemplate(string $overrideTemplate, string $fallbackTemplate): string
    {
        try {
            $this->twig->load($overrideTemplate);

            return $overrideTemplate;
        } catch (LoaderError) {
            return $fallbackTemplate;
        }
    }
}
