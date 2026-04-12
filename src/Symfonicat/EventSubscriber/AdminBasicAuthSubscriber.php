<?php

namespace Symfonicat\EventSubscriber;

use Symfonicat\Entity\Admin;
use Symfonicat\Repository\AdminRepository;
use Symfonicat\Service\AdminMfaService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminBasicAuthSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AdminRepository $adminRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
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

        $request->attributes->set('symfonicat_admin_mfa_verified', false);

        if (in_array($path, ['/admin/logout', '/admin/logout/clear'], true)) {
            $this->adminMfaService->clear($request);

            return;
        }

        [$username, $password] = $this->extractCredentials($request);
        if ($username === null || $password === null) {
            $this->adminMfaService->clear($request);
            $event->setResponse($this->challenge('Missing HTTP basic credentials.'));

            return;
        }

        $admin = $this->adminRepository->findOneByEmail($username);
        if ($admin === null || !$this->passwordHasher->isPasswordValid($admin, $password)) {
            $this->adminMfaService->clear($request);
            $event->setResponse($this->challenge('Invalid admin credentials.'));

            return;
        }

        $request->attributes->set('symfonicat_admin', $admin);
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
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function extractCredentials(Request $request): array
    {
        $header = trim((string) $request->headers->get('Authorization', ''));
        if (!str_starts_with(strtolower($header), 'basic ')) {
            return [null, null];
        }

        $encoded = substr($header, 6);
        $decoded = base64_decode($encoded, true);
        if ($decoded === false || !str_contains($decoded, ':')) {
            return [null, null];
        }

        [$username, $password] = explode(':', $decoded, 2);

        return [trim($username), $password];
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

    private function mfaNotConfigured(Admin $admin): Response
    {
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
}
