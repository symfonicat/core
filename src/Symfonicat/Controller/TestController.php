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

final class TestController extends AbstractController
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

    #[Route('/test', name: 'app_project_test', methods: ['GET'])]
    public function main(Request $request, string $path = ''): Response
    {

        return new Response('test');
    }
}
