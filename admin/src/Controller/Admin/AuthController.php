<?php

namespace Symfonicat\Controller\Admin;

use Symfonicat\Entity\Admin;
use Symfonicat\Service\AdminMfaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    #[Route('/admin/login', name: 'symfonicat_admin_login', methods: ['GET', 'POST'])]
    public function login(Request $request, AdminMfaService $adminMfaService, AuthenticationUtils $authenticationUtils): Response
    {
        $admin = $this->getUser();
        if (!$admin instanceof Admin) {
            return $this->render('@symfonicat/login.html.twig', [
                'admin' => null,
                'last_username' => $authenticationUtils->getLastUsername(),
                'error' => $authenticationUtils->getLastAuthenticationError(),
            ]);
        }

        if (!$admin->hasMfaSecret()) {
            return new Response(
                sprintf(
                    'Admin MFA is not configured for "%s". Re-run "bin/console symfonicat:admin:create %s". You will be prompted for the password.',
                    $admin->getEmail(),
                    $admin->getEmail(),
                ),
                Response::HTTP_FORBIDDEN,
                [
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma' => 'no-cache',
                    'Expires' => '0',
                ],
            );
        }

        if ($adminMfaService->isVerified($request, $admin)) {
            $targetPath = $adminMfaService->consumeTargetPath($request) ?? $this->generateUrl('symfonicat_admin_dashboard');

            return new RedirectResponse($targetPath);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('symfonicat_admin_mfa', (string) $request->request->get('_token'))) {
                $this->addFlash('debug', 'Invalid MFA form token.');
            } elseif ($adminMfaService->verify($admin, (string) $request->request->get('code'))) {
                $adminMfaService->markVerified($request, $admin);
                $targetPath = $adminMfaService->consumeTargetPath($request) ?? $this->generateUrl('symfonicat_admin_dashboard');

                return new RedirectResponse($targetPath);
            } else {
                $this->addFlash('debug', 'Invalid MFA code.');
            }
        }

        return $this->render('@symfonicat/login.html.twig', [
            'admin' => $admin,
        ]);
    }

    #[Route('/admin/login/check', name: 'symfonicat_admin_login_check', methods: ['POST'])]
    public function loginCheck(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route('/admin/logout', name: 'symfonicat_admin_logout', methods: ['GET'])]
    public function logout(Request $request, AdminMfaService $adminMfaService): Response
    {
        $adminMfaService->clear($request);

        return new RedirectResponse($this->generateUrl('symfonicat_admin_logout_clear'), Response::HTTP_SEE_OTHER, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    #[Route('/admin/logout/clear', name: 'symfonicat_admin_logout_clear', methods: ['GET'])]
    public function logoutClear(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
