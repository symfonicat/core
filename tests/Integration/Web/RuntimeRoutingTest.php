<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatWebTestCase;
use Symfonicat\Entity\Application;

final class RuntimeRoutingTest extends SymfonicatWebTestCase
{
    public function testDomainRootRendersDomainTemplate(): void
    {
        $this->createDomain('example.com');
        $this->setHost('example.com');

        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Main Domain Router', (string) $this->client()->getResponse()->getContent());
        self::assertStringContainsString('example.com', (string) $this->client()->getResponse()->getContent());
    }

    public function testSubdomainRendersWithPlainSubdomainId(): void
    {
        $domain = $this->createDomain('example.com');
        $this->createSubdomain('core/subdomain1', $domain)->setCatch(true);
        $this->entityManager()->flush();
        $this->setHost('subdomain1.example.com');

        $this->client()->request('GET', '/docs');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client()->getResponse()->getContent();
        self::assertStringContainsString('Main Subdomain Router', $content);
        self::assertStringContainsString('subdomain1', $content);
        self::assertStringNotContainsString('core/subdomain1', $content);
    }

    public function testEndpointArgumentsAndCatchRenderEndpointTemplate(): void
    {
        $endpoint = $this->createEndpoint('core/test')
            ->setArguments(['symfonicat', '*', 'test'])
            ->setCatch(true);
        $env = $this->createEnv('primary');
        $this->setEndpointEnv($endpoint, $env, 'purple');
        $this->setHost('example.com');

        $this->client()->request('GET', '/symfonicat/pizza/test/docs');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client()->getResponse()->getContent();
        self::assertStringContainsString('Main Endpoint Router', $content);
        self::assertStringContainsString('core/test', $content);
        self::assertStringContainsString('color: purple', $content);
    }

    public function testSymfonyRoutesStillWinOverRuntimeCatchRoutes(): void
    {
        $this->createDomain('example.com')->setCatch(true);
        $this->entityManager()->flush();
        $this->setHost('example.com');

        $this->client()->request('GET', '/test');

        self::assertResponseIsSuccessful();
        self::assertSame('test', (string) $this->client()->getResponse()->getContent());
    }

    public function testEndpointApplicationRendersThroughInternalApplicationEntry(): void
    {
        $endpoint = $this->createEndpoint('core/test')
            ->setArguments(['symfonicat', '*', 'test'])
            ->setCatch(true);
        $env = $this->createEnv('primary');
        $this->setEndpointEnv($endpoint, $env, 'endpoint');
        $application = $this->createApplication('Example Application', Application::TYPE_ENDPOINT, endpoint: $endpoint);
        $this->setApplicationEnv($application, $env, 'application');
        $this->setHost('example.com');

        $this->client()->request('GET', sprintf('/application/core/%s/docs', $application->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $this->client()->getResponse()->getContent();
        self::assertStringContainsString('Main Endpoint Router', $content);
        self::assertStringContainsString('core/test', $content);
        self::assertStringContainsString('color: application', $content);
        self::assertStringNotContainsString('color: endpoint', $content);
    }
}
