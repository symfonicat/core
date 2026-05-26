<?php

namespace App\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Symfonicat\EventSubscriber\AdminLockSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class AdminLockSubscriberTest extends TestCase
{
    private string $subdomainDir;

    protected function setUp(): void
    {
        $this->subdomainDir = sys_get_temp_dir().'/symfonicat_admin_lock_'.bin2hex(random_bytes(6));
        mkdir($this->subdomainDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $lockPath = $this->subdomainDir.'/symfonicat.lock';
        if (is_file($lockPath)) {
            unlink($lockPath);
        }

        if (is_dir($this->subdomainDir)) {
            rmdir($this->subdomainDir);
        }
    }

    public function testBlocksAdminPrefixedPathsWithoutLockFile(): void
    {
        $subscriber = new AdminLockSubscriber($this->subdomainDir);

        foreach (['/admin', '/admin/login', '/administrator'] as $path) {
            $event = $this->requestEvent($path);

            try {
                $subscriber->onKernelRequest($event);
                self::fail(sprintf('%s must be blocked without symfonicat.lock', $path));
            } catch (NotFoundHttpException $exception) {
                $this->assertLockedAdminException($exception);
            }
        }
    }

    public function testAllowsAdminPrefixedPathsWithLockFile(): void
    {
        file_put_contents($this->subdomainDir.'/symfonicat.lock', "enabled\n");

        $event = $this->requestEvent('/admin/login');

        (new AdminLockSubscriber($this->subdomainDir))->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testBlocksAdminSubRequestsWithoutLockFile(): void
    {
        $event = $this->requestEvent('/admin/login', HttpKernelInterface::SUB_REQUEST);

        $this->expectException(NotFoundHttpException::class);

        (new AdminLockSubscriber($this->subdomainDir))->onKernelRequest($event);
    }

    public function testAllowsSymfonyExceptionRenderingSubRequestWithoutLockFile(): void
    {
        $event = $this->requestEvent('/admin/login', HttpKernelInterface::SUB_REQUEST, [], [
            'exception' => new NotFoundHttpException('Not Found'),
        ]);

        (new AdminLockSubscriber($this->subdomainDir))->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testBlocksCaddyLockedRewriteWithoutLockFile(): void
    {
        $event = $this->requestEvent('/index.php', HttpKernelInterface::MAIN_REQUEST, [
            'HTTP_X_SYMFONICAT_ADMIN_LOCKED' => '1',
        ]);

        try {
            (new AdminLockSubscriber($this->subdomainDir))->onKernelRequest($event);
            self::fail('Caddy-routed locked admin requests must become Symfony 404s.');
        } catch (NotFoundHttpException $exception) {
            $this->assertLockedAdminException($exception);
        }
    }

    public function testIgnoresPublicPathsWithoutLockFile(): void
    {
        $subscriber = new AdminLockSubscriber($this->subdomainDir);

        foreach (['/', '/docs', '/about'] as $path) {
            $event = $this->requestEvent($path);

            $subscriber->onKernelRequest($event);

            self::assertFalse($event->hasResponse(), sprintf('%s must not be handled by the admin lock', $path));
        }
    }

    /**
     * @param array<string, string> $server
     * @param array<string, mixed> $attributes
     */
    private function requestEvent(string $path, int $type = HttpKernelInterface::MAIN_REQUEST, array $server = [], array $attributes = []): RequestEvent
    {
        $request = Request::create($path, 'GET', [], [], [], $server);
        $request->attributes->add($attributes);

        return new RequestEvent(
            new class implements HttpKernelInterface {
                public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
                {
                    return new Response();
                }
            },
            $request,
            $type,
        );
    }

    private function assertLockedAdminException(NotFoundHttpException $exception): void
    {
        self::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        self::assertSame('no-store, no-cache, must-revalidate, max-age=0, private', $exception->getHeaders()['Cache-Control'] ?? null);
        self::assertSame('no-cache', $exception->getHeaders()['Pragma'] ?? null);
        self::assertSame('0', $exception->getHeaders()['Expires'] ?? null);
        self::assertSame('noindex, nofollow', $exception->getHeaders()['X-Robots-Tag'] ?? null);
    }
}
