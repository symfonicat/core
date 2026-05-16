<?php

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Symfonicat HTTP/integration tests.
 *
 * Centralizes two patterns we lean on heavily:
 *   - Per-test truncation (delete-based), so fixtures are explicit.
 *   - A createClientWithHost() helper so tests can describe the incoming
 *     Host header declaratively rather than twiddling server params.
 */
abstract class SymfonicatWebTestCase extends WebTestCase
{
    use DatabaseFixtureTrait;

    /**
     * Tracks the client the test created so we share its container when
     * calling fixture helpers mid-test (WebTestCase reboots on createClient).
     */
    private ?KernelBrowser $client = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->truncateSymfonicatTables();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->client = null;
        self::ensureKernelShutdown();
    }

    /**
     * Hand the test back the shared browser client. Most tests want the one
     * created in setUp so their fixtures and their requests share a container.
     */
    protected function client(): KernelBrowser
    {
        if ($this->client === null) {
            $this->client = static::createClient();
        }

        return $this->client;
    }

    /**
     * Make every subsequent request on this client go out with the given Host
     * header. Request::getHost() honors HTTP_HOST, so this is how we simulate
     * `example.com`, `subdomain1.example.com`, etc. against Symfony's router.
     */
    protected function setHost(string $host): void
    {
        $this->client()->setServerParameter('HTTP_HOST', $host);
    }

    protected static function getTestContainer(): ContainerInterface
    {
        return static::getContainer();
    }
}
