<?php

namespace Symfonicat\Security;

use Psr\Cache\CacheItemPoolInterface;
use Symfonicat\Entity\Admin;
use Symfonicat\Repository\AdminRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class AdminUserProvider implements UserProviderInterface
{
    private const CACHE_PREFIX = 'symfonicat_core_user_';
    private const CACHE_TTL = 300;

    public function __construct(
        private readonly AdminRepository $adminRepository,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $email = $this->normalizeEmail($identifier);
        if ($email === '') {
            throw new UserNotFoundException('Admin email must not be empty.');
        }

        $cacheItem = $this->cache->getItem($this->cacheKey($email));
        if ($cacheItem->isHit()) {
            $cachedAdmin = $cacheItem->get();
            if (is_array($cachedAdmin)) {
                return $this->hydrate($cachedAdmin);
            }
        }

        $admin = $this->adminRepository->findOneByEmail($email);
        if (!$admin instanceof Admin) {
            throw new UserNotFoundException(sprintf('Admin "%s" not found.', $email));
        }

        $cacheItem->set($this->snapshot($admin));
        $cacheItem->expiresAfter(self::CACHE_TTL);
        $this->cache->save($cacheItem);

        return $admin;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Admin) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return is_a($class, Admin::class, true);
    }

    public function clearCache(string|Admin $admin): void
    {
        $email = $admin instanceof Admin ? $admin->getUserIdentifier() : $this->normalizeEmail($admin);
        if ($email === '') {
            return;
        }

        $this->cache->deleteItem($this->cacheKey($email));
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function cacheKey(string $email): string
    {
        return self::CACHE_PREFIX . hash('sha256', $this->normalizeEmail($email));
    }

    /**
     * @return array{
     *     email: string,
     *     mfaSecret: ?string,
     *     password: string,
     *     roles: array<int, string>
     * }
     */
    private function snapshot(Admin $admin): array
    {
        return [
            'email' => $admin->getEmail(),
            'mfaSecret' => $admin->getMfaSecret(),
            'password' => $admin->getPassword(),
            'roles' => $admin->getRoles(),
        ];
    }

    /**
     * @param array{
     *     email: string,
     *     mfaSecret: ?string,
     *     password: string,
     *     roles: array<int, string>
     * } $snapshot
     */
    private function hydrate(array $snapshot): Admin
    {
        return (new Admin())
            ->setEmail($snapshot['email'])
            ->setMfaSecret($snapshot['mfaSecret'])
            ->setPassword($snapshot['password'])
            ->setRoles($snapshot['roles']);
    }
}
