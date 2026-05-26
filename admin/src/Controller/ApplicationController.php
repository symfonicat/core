<?php

namespace Symfonicat\Controller;

use Symfonicat\Entity\Application;
use Symfonicat\Service\ApplicationService;
use Symfonicat\Service\RuntimeRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ApplicationController extends AbstractController
{
    public function __construct(
        private readonly ApplicationService $applicationService,
        private readonly RuntimeRenderer $runtimeRenderer,
    ) {
    }

    #[Route('/application/{vendor}/{id}/{path}', name: 'symfonicat_application', requirements: ['path' => '.*'], defaults: ['path' => ''], methods: ['GET'])]
    public function application(Request $request, string $vendor, string $id, string $path = ''): Response
    {
        $application = $this->applicationService->find(trim($vendor, '/').'/'.trim($id, '/'))
            ?? $this->applicationService->find(trim($id, '/'));

        if (!$application instanceof Application) {
            throw new NotFoundHttpException(sprintf('Application "%s/%s" was not found.', $vendor, $id));
        }

        return $this->renderApplication($request, $application, $path);
    }

    public function renderApplication(Request $request, Application $application, string $path = ''): Response
    {
        $request->attributes->set('application', $application);
        $request->attributes->set('symfonicat_application_path', '/'.trim($path, '/'));

        if ($application->isEndpointType() && $application->getEndpoint() !== null) {
            $request->attributes->set('endpoint', $application->getEndpoint());

            return $this->runtimeRenderer->render($request, RuntimeRenderer::TARGET_ENDPOINT);
        }

        if ($application->isSubdomainType() && $application->getSubdomain() !== null) {
            $request->attributes->set('subdomain', $application->getSubdomain());
            if ($application->getDomain() !== null) {
                $request->attributes->set('domain', $application->getDomain());
            }

            return $this->runtimeRenderer->render($request, RuntimeRenderer::TARGET_SUBDOMAIN);
        }

        if ($application->isDomainType() && $application->getDomain() !== null) {
            $request->attributes->set('domain', $application->getDomain());

            return $this->runtimeRenderer->render($request, RuntimeRenderer::TARGET_DOMAIN);
        }

        throw new NotFoundHttpException(sprintf('Application "%s" does not have a renderable target.', $application->getId()));
    }
}
