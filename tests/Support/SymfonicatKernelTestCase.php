<?php

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for tests that need a container but not a full web stack.
 *
 * Boots the kernel once per test and truncates every Symfonicat table in setUp
 * so tests can seed exactly the rows they care about without leaking state.
 */
abstract class SymfonicatKernelTestCase extends KernelTestCase
{
    use DatabaseFixtureTrait;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->truncateSymfonicatTables();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        self::ensureKernelShutdown();
    }

    protected static function getTestContainer(): ContainerInterface
    {
        return static::getContainer();
    }
}
