<?php

namespace Symfonicat\Controller;

use Symfonicat\Service\DomainService;
use Symfonicat\Service\ProjectService;
use Symfonicat\Service\SubdomainService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MainController extends AbstractController
{
    #[Route('/', name: 'app_project_root', methods: ['GET'], defaults: ['path' => ''], condition: 'not request.attributes.get("project")')]
    #[Route('/{path}', name: 'app_project', methods: ['GET'], requirements: ['path' => '(?!m(?:/|$)).*'], defaults: ['path' => ''], priority: -1000, condition: 'request.attributes.get("project")')]
    public function main(
        DomainService $domainService,
        ProjectService $projectService,
        SubdomainService $subdomainService,
        string $path = ''
    ): Response {
        if (!$subdomainService->getSubdomains()) {
            $domain = $domainService->load();
            if (!$domain) {
                throw $this->createNotFoundException();
            }

            return $this->render('domain/main.html.twig', [
                'domain' => $domain,
            ]);
        }

        if (!$projectService->load()) {
            throw $this->createNotFoundException();
        }

        return $this->render('project/main.html.twig');
    }
}
