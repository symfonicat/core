<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

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

    public function testDomainRendersOnNonRootPathWithoutCatch(): void
    {
        $this->createDomain('example.com');
        $this->setHost('example.com');

        $this->client()->request('GET', '/docs');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client()->getResponse()->getContent();
        self::assertStringContainsString('Main Domain Router', $content);
        self::assertStringContainsString('example.com', $content);
    }

    public function testSubdomainRendersWithPlainSubdomainIdOnNonRootPathWithoutCatch(): void
    {
        $domain = $this->createDomain('example.com');
        $this->createSubdomain('core/subdomain1', $domain);
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

    public function testApplicationTwigVariableIsStillAvailableFromBuildContext(): void
    {
        $application = $this->createApplication('Example Application');
        $requestStack = self::getTestContainer()->get(RequestStack::class);
        $request = Request::create('/');
        $request->attributes->set('application', $application);
        $requestStack->push($request);

        /** @var Environment $twig */
        $twig = self::getTestContainer()->get(Environment::class);

        try {
            $html = $twig->createTemplate("{% extends 'base.html.twig' %}{% block body %}{% endblock %}")->render([
                'domain' => null,
                'subdomain' => null,
                'endpoint' => null,
            ]);

            self::assertStringContainsString('window.application = {', $html);
            self::assertStringContainsString('"name": "Example Application"', $html);
        } finally {
            $requestStack->pop();
        }
    }

    public function testApplicationPathDoesNotResolveAsPublicRuntime(): void
    {
        $this->setHost('example.com');
        $client = $this->client();
        $client->catchExceptions(false);

        $this->expectException(NotFoundHttpException::class);
        $client->request('GET', '/application/core/example/docs');
    }
}
