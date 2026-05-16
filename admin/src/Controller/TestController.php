<?php

namespace Symfonicat\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TestController extends AbstractController
{
    #[Route('/test', name: 'symfonicat_project_test', methods: ['GET'])]
    public function main(): Response
    {

        return new Response('test');
    }
}
