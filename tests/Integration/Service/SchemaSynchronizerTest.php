<?php

namespace App\Tests\Integration\Service;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Service\MiddlewareClassProvider;

final class SchemaSynchronizerTest extends SymfonicatKernelTestCase
{
    public function testProviderIncludesPackageMiddlewareClassesFromSrcDirectories(): void
    {
        /** @var MiddlewareClassProvider $provider */
        $provider = self::getTestContainer()->get(MiddlewareClassProvider::class);
        $classes = $provider->classes();

        self::assertContains('Symfonicat\\Middleware\\AnalyticsMiddleware', $classes);
        self::assertContains('Symfonicat\\Middleware\\DomainMiddleware', $classes);
    }
}
