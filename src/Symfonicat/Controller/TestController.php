<?php

namespace Symfonicat\Controller;

use Symfonicat\Entity\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TestController extends AbstractController
{
    #[Route('/test', name: 'app_project_test', methods: ['GET'])]
    public function main(?Application $application = null): Response
    {
        if ($application instanceof Application) {
            return new Response(sprintf('test %s', $application->getId()));
        }

        return new Response('test');
    }
}
