<?php

namespace Symfonicat\Controller;

use Symfonicat\Entity\Application;
use Symfonicat\Repository\ApplicationRepository;
use Symfonicat\Service\ApplicationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;
use Twig\Error\LoaderError;

final class ApplicationController extends AbstractController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly ApplicationRepository $applicationRepository,
        private readonly ApplicationService $applicationService,
    ) {
    }

    #[Route('/application/{id}/{path}', name: 'symfonicat_application', requirements: ['path' => '.*'], defaults: ['path' => ''], methods: ['GET'])]
    public function application(
        Request $request,
        string $id,
        string $path,
    ): Response {
        $application = $this->applicationRepository->findOneByFullOrCleanId($id);
        if (!$application instanceof Application) {
            throw new NotFoundHttpException(sprintf('Application "%s" was not found.', $id));
        }

        if ($this->applicationService->getRuleForApplication($application) === null) {
            throw new NotFoundHttpException(sprintf('Application "%s" does not have an application routing rule.', $id));
        }

        $applicationPath = $this->applicationService->path($id, $path);

        return $this->renderApplication($request, $application, $applicationPath, $applicationPath);
    }

    public function renderApplication(
        Request $request,
        Application $application,
        string $applicationPath = '',
        ?string $redirectTarget = null,
    ): Response {
        $request->attributes->set('application', $application);
        $request->attributes->set('symfonicat_application_path', $applicationPath);
        if ($redirectTarget !== null && $redirectTarget !== '') {
            $request->attributes->set('symfonicat_application_redirect_target', $redirectTarget);
        }
        $request->attributes->set('symfonicat_routing_rule_active', true);

        return $this->render($this->resolveTemplate($application));
    }

    private function resolveTemplate(Application $application): string
    {
        $override = sprintf('application/overrides/%s.html.twig', $application->getId());

        try {
            $this->twig->load($override);

            return $override;
        } catch (LoaderError) {
            return 'application/main.html.twig';
        }
    }
}
