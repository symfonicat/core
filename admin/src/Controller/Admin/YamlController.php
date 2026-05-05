<?php

namespace Symfonicat\Controller\Admin;

use Symfonicat\Service\AdminYaml;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class YamlController extends AbstractController
{
    #[Route('/admin/y/dump', name: 'symfonicat_admin_yaml_dump', methods: ['GET'])]
    public function dump(Request $request, AdminYaml $adminYaml): RedirectResponse
    {
        $adminYaml->dump();
        $this->addFlash('success', 'yaml successfully dumped');

        return $this->redirectAfterAction($request);
    }

    #[Route('/admin/y/load', name: 'symfonicat_admin_yaml_load', methods: ['GET'])]
    public function load(Request $request, AdminYaml $adminYaml): RedirectResponse
    {
        $adminYaml->load();
        $this->addFlash('success', 'yaml successfully loaded');

        return $this->redirectAfterAction($request);
    }

    private function redirectAfterAction(Request $request): RedirectResponse
    {
        $referer = $request->headers->get('referer');
        if (is_string($referer) && $referer !== '' && !str_contains($referer, '/admin/y/')) {
            return $this->redirect($referer, Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('symfonicat_admin_dashboard', [], Response::HTTP_SEE_OTHER);
    }
}
