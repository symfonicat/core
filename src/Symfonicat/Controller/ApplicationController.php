<?php

namespace Symfonicat\Controller;

use Symfonicat\Entity\Application;
use Symfonicat\Repository\ApplicationRepository;
use Symfonicat\Service\ApplicationUrlService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;
use Twig\Error\LoaderError;

final class ApplicationController extends AbstractController
{
    #[Route('/application/{id}/{path}', name: 'symfonicat_application', requirements: ['path' => '.*'], defaults: ['path' => ''], methods: ['GET'])]
    public function application(
        Request $request,
        string $id,
        string $path,
        ApplicationRepository $applicationRepository,
        ApplicationUrlService $applicationUrlService,
        Environment $twig,
    ): Response {
        $application = $applicationRepository->find($id);
        if (!$application instanceof Application) {
            throw new NotFoundHttpException(sprintf('Application "%s" was not found.', $id));
        }

        if ($applicationUrlService->getRuleForApplication($application) === null) {
            throw new NotFoundHttpException(sprintf('Application "%s" does not have an application routing rule.', $id));
        }

        $applicationPath = $applicationUrlService->path($id, $path);

        $request->attributes->set('application', $application);
        $request->attributes->set('symfonicat_application_path', $applicationPath);
        $request->attributes->set('symfonicat_application_redirect_target', $applicationPath);
        $request->attributes->set('symfonicat_routing_rule_active', true);

        return $this->render($this->resolveTemplate($application, $twig));
    }

    private function resolveTemplate(Application $application, Environment $twig): string
    {
        $override = sprintf('application/overrides/%s.html.twig', $application->getId());

        try {
            $twig->load($override);

            return $override;
        } catch (LoaderError) {
            return 'application/main.html.twig';
        }
    }
}
