<?php

namespace Symfonicat\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    #[Route('/healthz', name: 'symfonicat_health_check', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response('ok', Response::HTTP_OK, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
