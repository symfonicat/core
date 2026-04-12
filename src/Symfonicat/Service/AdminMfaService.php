<?php

namespace Symfonicat\Service;

use OTPHP\TOTP;
use Symfonicat\Entity\Admin;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;

final class AdminMfaService
{
    public const ISSUER = 'Symfonicat Admin';

    private const SESSION_KEY = 'symfonicat_admin_mfa';
    private const TARGET_PATH_KEY = 'symfonicat_admin_mfa_target';

    public function ensureSecret(Admin $admin): void
    {
        if ($admin->hasMfaSecret()) {
            return;
        }

        $admin->setMfaSecret(TOTP::generate(new NativeClock())->getSecret());
    }

    public function getSecret(Admin $admin): ?string
    {
        return $admin->getMfaSecret();
    }

    public function getProvisioningUri(Admin $admin): ?string
    {
        $totp = $this->createTotp($admin);

        return $totp?->getProvisioningUri();
    }

    public function verify(Admin $admin, string $code): bool
    {
        $totp = $this->createTotp($admin);
        if ($totp === null) {
            return false;
        }

        $normalizedCode = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6,8}$/', $normalizedCode)) {
            return false;
        }

        return $totp->verify($normalizedCode, null, 29);
    }

    public function markVerified(Request $request, Admin $admin): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $request->getSession()->set(self::SESSION_KEY, [
            'admin' => $admin->getUserIdentifier(),
            'fingerprint' => $this->getAuthorizationFingerprint($request),
        ]);
    }

    public function isVerified(Request $request, Admin $admin): bool
    {
        if (!$request->hasSession()) {
            return false;
        }

        $state = $request->getSession()->get(self::SESSION_KEY);
        if (!is_array($state)) {
            return false;
        }

        $adminIdentifier = (string) ($state['admin'] ?? '');
        $fingerprint = (string) ($state['fingerprint'] ?? '');

        return hash_equals($admin->getUserIdentifier(), $adminIdentifier)
            && hash_equals($this->getAuthorizationFingerprint($request), $fingerprint);
    }

    public function clear(Request $request): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $session->remove(self::SESSION_KEY);
        $session->remove(self::TARGET_PATH_KEY);
    }

    public function rememberTargetPath(Request $request): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $path = $request->getRequestUri();
        if (
            $path === ''
            || str_starts_with($path, '/admin/login')
            || str_starts_with($path, '/admin/logout')
        ) {
            return;
        }

        $request->getSession()->set(self::TARGET_PATH_KEY, $path);
    }

    public function consumeTargetPath(Request $request): ?string
    {
        if (!$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();
        $targetPath = $session->get(self::TARGET_PATH_KEY);
        $session->remove(self::TARGET_PATH_KEY);

        return is_string($targetPath) && str_starts_with($targetPath, '/admin')
            ? $targetPath
            : null;
    }

    private function createTotp(Admin $admin): ?TOTP
    {
        $secret = $admin->getMfaSecret();
        if ($secret === null || $secret === '') {
            return null;
        }

        $totp = TOTP::createFromSecret($secret, new NativeClock());
        $totp->setLabel($admin->getEmail());
        $totp->setIssuer(self::ISSUER);

        return $totp;
    }

    private function getAuthorizationFingerprint(Request $request): string
    {
        $header = trim((string) $request->headers->get('Authorization', ''));

        return hash('sha256', $header);
    }
}
