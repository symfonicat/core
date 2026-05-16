<?php

namespace Symfonicat\Controller;

use Kafkiansky\SymfonyMiddleware\Attribute\Middleware as MiddlewareAttribute;
use Symfonicat\Middleware\TestMiddleware;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[MiddlewareAttribute([TestMiddleware::class])]
final class TestController extends AbstractController
{
    #[Route('/test', name: 'symfonicat_subdomain_test', methods: ['GET'])]
    public function main(): Response
    {

        return new Response('test');
    }
}
