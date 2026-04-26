<?php

namespace Symfonicat\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'symfonicat_admin_dashboard', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('symfonicat_domain_index');
    }
}
