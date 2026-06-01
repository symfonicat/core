<?php

namespace Symfonicat\Controller;

use Symfonicat\Service\RuntimeRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RuntimeController extends AbstractController
{
    public function __construct(
        private readonly RuntimeRenderer $runtimeRenderer,
    ) {
    }

    #[Route('/', name: 'symfonicat_runtime_root', methods: ['GET'], defaults: ['path' => ''], condition: 'request.attributes.get("symfonicat_runtime_route_allowed")')]
    #[Route('/{path}', name: 'symfonicat_runtime', methods: ['GET'], requirements: ['path' => '(?!core(?:/|$)|application(?:/|$)|m(?:/|$)).*'], defaults: ['path' => ''], priority: -1000, condition: 'request.attributes.get("symfonicat_runtime_route_allowed")')]
    public function main(Request $request, string $path = ''): Response
    {
        return $this->runtimeRenderer->render($request);
    }
}
