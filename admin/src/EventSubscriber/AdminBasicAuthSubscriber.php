<?php

namespace Symfonicat\EventSubscriber;

use Symfonicat\Entity\Admin;
use Symfonicat\Service\AdminMfaService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;

final class AdminBasicAuthSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly AdminMfaService $adminMfaService,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $event->hasResponse()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/admin')) {
            return;
        }

        $admin = $this->security->getUser();
        if (!$admin instanceof Admin) {
            return;
        }

        if ($path === '/admin/logout') {
            $this->adminMfaService->clear($request);

            return;
        }

        if (!$admin->hasMfaSecret()) {
            $event->setResponse($this->mfaNotConfigured($admin));

            return;
        }

        if ($this->adminMfaService->isVerified($request, $admin)) {
            $request->attributes->set('symfonicat_admin_mfa_verified', true);

            return;
        }

        if ($path === '/admin/login') {
            return;
        }

        $this->adminMfaService->rememberTargetPath($request);
        $event->setResponse(new RedirectResponse('/admin/login'));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -8],
        ];
    }

    private function mfaNotConfigured(Admin $admin): Response
    {
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
}
