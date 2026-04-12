<?php

namespace Symfonicat\Controller\Admin;

use Symfonicat\Entity\Admin;
use Symfonicat\Service\AdminMfaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    #[Route('/admin/login', name: 'symfonicat_admin_login', methods: ['GET', 'POST'])]
    public function login(Request $request, AdminMfaService $adminMfaService): Response
    {
        $admin = $request->attributes->get('symfonicat_admin');
        if (!$admin instanceof Admin) {
            return $this->challenge('Use HTTP basic credentials for the Symfonicat admin area.');
        }

        if (!$admin->hasMfaSecret()) {
            return new Response(
                sprintf(
                    'Admin MFA is not configured for "%s". Re-run "bin/console symfonicat:admin:create %s".',
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

        return $this->render('admin/login.html.twig', [
            'admin' => $admin,
        ]);
    }

    #[Route('/admin/logout', name: 'symfonicat_admin_logout', methods: ['GET'])]
    public function logout(Request $request, AdminMfaService $adminMfaService): Response
    {
        $adminMfaService->clear($request);
        $clearUrl = $this->generateUrl('symfonicat_admin_logout_clear');
        $redirectUrl = $this->generateUrl('app_project_root');
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
        <meta http-equiv="Pragma" content="no-cache">
        <meta http-equiv="Expires" content="0">
        <meta name="robots" content="noindex, nofollow">
        <title>Logging out</title>
    </head>
    <body>
        <script>
            (function () {
                const clearUrl = {$this->jsonEncodeForScript($clearUrl)};
                const redirectUrl = {$this->jsonEncodeForScript($redirectUrl)};
                const username = 'logout';
                const password = String(Date.now());
                const redirect = () => window.location.replace(redirectUrl);
                let redirected = false;

                const safeRedirect = () => {
                    if (redirected) {
                        return;
                    }

                    redirected = true;
                    redirect();
                };

                try {
                    if (typeof document.execCommand === 'function') {
                        document.execCommand('ClearAuthenticationCache');
                    }
                } catch (error) {
                }

                try {
                    const xhr = new XMLHttpRequest();
                    xhr.open('GET', clearUrl, true, username, password);
                    xhr.onloadend = safeRedirect;
                    xhr.onerror = safeRedirect;
                    xhr.send('');
                } catch (error) {
                    try {
                        fetch(clearUrl, {
                            method: 'GET',
                            headers: {
                                Authorization: 'Basic ' + btoa(username + ':' + password),
                            },
                            cache: 'no-store',
                        }).finally(safeRedirect);
                    } catch (fetchError) {
                        safeRedirect();
                    }
                }

                window.setTimeout(safeRedirect, 750);
            }());
        </script>
        <noscript>
            <meta http-equiv="refresh" content="0;url={$redirectUrl}">
        </noscript>
    </body>
</html>
HTML;

        return new Response(
            $html,
            Response::HTTP_OK,
            [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Clear-Site-Data' => '"cache"',
            ],
        );
    }

    #[Route('/admin/logout/clear', name: 'symfonicat_admin_logout_clear', methods: ['GET'])]
    public function logoutClear(Request $request, AdminMfaService $adminMfaService): Response
    {
        $adminMfaService->clear($request);

        return new Response(
            'Admin credentials cleared.',
            Response::HTTP_UNAUTHORIZED,
            [
                'WWW-Authenticate' => 'Basic realm="Symfonicat Admin"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ],
        );
    }

    private function challenge(string $message): Response
    {
        return new Response(
            $message,
            Response::HTTP_UNAUTHORIZED,
            [
                'WWW-Authenticate' => 'Basic realm="Symfonicat Admin"',
            ],
        );
    }

    private function jsonEncodeForScript(string $value): string
    {
        return (string) json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR,
        );
    }
}
