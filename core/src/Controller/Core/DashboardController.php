<?php

namespace Symfonicat\Controller\Core;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/core', name: 'symfonicat_core_dashboard', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('symfonicat_domain_index');
    }
}
