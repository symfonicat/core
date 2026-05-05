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
    ) {
        $this->domain = $this->domainService->load();
        $this->project = $this->projectService->load();
    }

    #[Route('/', name: 'symfonicat_project_root', methods: ['GET'], defaults: ['path' => ''], condition: 'not request.attributes.get("project")')]
    #[Route('/{path}', name: 'symfonicat_project', methods: ['GET'], requirements: ['path' => '(?!m(?:/|$)).*'], defaults: ['path' => ''], priority: -1000, condition: 'request.attributes.get("project") and request.attributes.get("symfonicat_use_project_catch_all", true)')]
    public function main(Request $request, string $path = ''): Response
    {
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
