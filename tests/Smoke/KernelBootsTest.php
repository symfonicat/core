<?php

namespace App\Tests\Smoke;

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Smoke-test that the Symfony kernel can build in the test environment.
 *
 * This is intentionally lightweight — it does not require a running database,
 * Redis, Mercure, or any external service. It only asserts that the container
 * compiles and a couple of core Symfonicat services are wired.
 */
final class KernelBootsTest extends KernelTestCase
{
    public function testKernelBootsInTestEnv(): void
    {
        self::bootKernel(['environment' => 'test', 'debug' => false]);

        $kernel = self::$kernel;
        self::assertInstanceOf(Kernel::class, $kernel);
        self::assertSame('test', $kernel->getEnvironment());

        $container = static::getContainer();
        self::assertTrue($container->has('router'), 'router service must be wired');
        self::assertTrue($container->has('event_dispatcher'), 'event_dispatcher service must be wired');
    }
}
