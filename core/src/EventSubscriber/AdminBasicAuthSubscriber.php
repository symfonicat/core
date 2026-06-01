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

        if (!str_starts_with($path, '/core')) {
            return;
        }

        $admin = $this->security->getUser();
        if (!$admin instanceof Admin) {
            return;
        }

        if ($path === '/core/logout') {
            $this->adminMfaService->clear($request);

            return;
        }

        if (!$admin->hasMfaSecret()) {
            $event->setResponse($this->mfaNotConfigured($admin));

            return;
        }

        if ($this->adminMfaService->isVerified($request, $admin)) {
            $request->attributes->set('symfonicat_core_mfa_verified', true);

            return;
        }

        if ($path === '/core/login') {
            return;
        }

        $this->adminMfaService->rememberTargetPath($request);
        $event->setResponse(new RedirectResponse('/core/login'));
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
