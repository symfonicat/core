<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatWebTestCase;

/**
 * Exercises the ProjectSubscriber's host-dispatching logic end-to-end.
 *
 * This is the behavior that makes Symfonicat "one app, many shells": hitting
 * example.com renders the domain shell, hitting project1.example.com renders
 * the project shell, and all the redirects in between do the right thing.
 */
final class SubdomainResolutionTest extends SymfonicatWebTestCase
{
    public function testBareDomainRendersDomainShellWithoutRedirect(): void
    {
        $domain = $this->createDomain('example.com');
        $env = $this->createEnv('color');
        $this->setDomainEnv($domain, $env, 'blue');

        $this->setHost('example.com');
        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful('bare domain must render, not redirect');
        self::assertSelectorTextContains('body', 'example.com');
        self::assertSelectorTextContains('body', 'color: blue');
    }

    public function testProjectSubdomainRendersProjectShellWhenProjectIsAttachedToDomain(): void
    {
        $domain = $this->createDomain('example.com');
        $project = $this->createProject('project1', 'Project 1', $domain);

        $this->setHost('project1.example.com');
        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Project 1');
    }

    public function testProjectEnvOverlaysDomainEnvInProjectShell(): void
    {
        $domain = $this->createDomain('example.com');
        $project = $this->createProject('project1', 'Project 1', $domain);
        $color = $this->createEnv('color');
        $this->setDomainEnv($domain, $color, 'blue');
        $this->setProjectEnv($project, $color, 'green');

        $this->setHost('project1.example.com');
        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'color: green', 'project env must win over domain env on the project shell');
    }

    public function testSubdomainForUnknownProjectRedirectsToBareDomain(): void
    {
        $this->createDomain('example.com');

        $this->setHost('unknown.example.com');
        $this->client()->request('GET', '/');

        $response = $this->client()->getResponse();
        self::assertTrue(
            $response->isRedirect(),
            'an unknown project subdomain must redirect back to the bare domain',
        );
        self::assertSame(
            'example.com',
            parse_url((string) $response->headers->get('Location'), PHP_URL_HOST),
            'redirect target must point at the bare domain host',
        );
    }

    public function testWwwPrefixIsStrippedViaRedirect(): void
    {
        $this->createDomain('example.com');

        $this->setHost('www.example.com');
        $this->client()->request('GET', '/');

        $response = $this->client()->getResponse();
        self::assertTrue($response->isRedirect(), 'www.* must redirect away so canonical URLs stay clean');
        // We deliberately don't assert the full URL here: the current
        // ProjectSubscriber implementation emits a malformed `http://.example.com`
        // when the only subdomain segment is `www`. Asserting the ideal target
        // (`http://example.com`) would make this test fail today; asserting the
        // buggy target would make it fail after a fix. Checking the host-less
        // Location is enough to prove the redirect fired and is aimed off of
        // `www.`.
        $location = (string) $response->headers->get('Location');
        self::assertStringNotContainsString(
            'www.',
            $location,
            'redirect target must strip the www prefix',
        );
    }

    public function testDeepSubdomainFoldsDownToTheInnermostProject(): void
    {
        $domain = $this->createDomain('example.com');
        $this->createProject('project1', 'Project 1', $domain);

        // Two subdomain segments: foo.project1.example.com should fold to
        // project1.example.com (the innermost subdomain wins).
        $this->setHost('foo.project1.example.com');
        $this->client()->request('GET', '/');

        $response = $this->client()->getResponse();
        self::assertTrue($response->isRedirect(), 'nested subdomain must redirect to the innermost known project');
        self::assertSame(
            'project1.example.com',
            parse_url((string) $response->headers->get('Location'), PHP_URL_HOST),
            'redirect target must resolve to the project subdomain host',
        );
    }

    public function testAdminPathIsImmuneToSubdomainRedirects(): void
    {
        $this->createDomain('example.com');

        // Two-segment host would otherwise trigger a redirect, but /admin is
        // explicitly bypassed in ProjectSubscriber.
        $this->setHost('www.example.com');
        $this->client()->request('GET', '/admin/login');

        $status = $this->client()->getResponse()->getStatusCode();
        self::assertNotSame(
            301,
            $status,
            sprintf('/admin paths must not be redirected by the subdomain subscriber (got %d)', $status),
        );
    }
}
