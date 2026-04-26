<?php

namespace Symfonicat\Controller\Module;

use Symfonicat\Controller\AbstractModuleController;
use Symfonicat\Service\Module\AnalyticsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/m/analytics')]
final class AnalyticsController extends AbstractModuleController
{
    #[Route('', name: 'symfonicat_module_analytics', methods: ['POST'])]
    public function index(AnalyticsService $analyticsService): Response
    {
        return $this->module(new JsonResponse([
            'working' => true,
        ]));
    }
}
