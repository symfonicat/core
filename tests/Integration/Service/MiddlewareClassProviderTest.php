<?php

namespace App\Tests\Integration\Service;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Service\MiddlewareClassProvider;

final class MiddlewareClassProviderTest extends SymfonicatKernelTestCase
{
    public function testChoicesAreGroupedByPackageBucketAndUseShortClassLabels(): void
    {
        /** @var MiddlewareClassProvider $provider */
        $provider = self::getTestContainer()->get(MiddlewareClassProvider::class);
        $choices = $provider->choices();

        self::assertArrayHasKey('core', $choices);
        self::assertSame(
            'Symfonicat\\Middleware\\DomainMiddleware',
            $choices['core']['DomainMiddleware'],
        );
        self::assertArrayHasKey('symfonicat/analytics', $choices);
        self::assertSame(
            'Symfonicat\\Middleware\\AnalyticsMiddleware',
            $choices['symfonicat/analytics']['AnalyticsMiddleware'],
        );
    }
}
