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
        $env = $this->createEnv('primary');
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
        $color = $this->createEnv('primary');
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
        self::assertSame(
            301,
            $response->getStatusCode(),
            'unknown project subdomains must 301 so search engines canonicalize on the bare domain',
        );
        self::assertSame(
            'http://example.com',
            (string) $response->headers->get('Location'),
            'redirect target must be exactly the bare-domain URL',
        );
    }

    public function testWwwPrefixIsStrippedViaRedirect(): void
    {
        $this->createDomain('example.com');

        $this->setHost('www.example.com');
        $this->client()->request('GET', '/');

        $response = $this->client()->getResponse();
        self::assertSame(
            301,
            $response->getStatusCode(),
            'www.* must 301 so canonical URLs stay clean',
        );
        self::assertSame(
            'http://example.com',
            (string) $response->headers->get('Location'),
            'www-only hosts must redirect to the bare domain, not to "http://.example.com"',
        );
    }

    public function testWwwPrefixIsStrippedButInnerSubdomainSurvives(): void
    {
        $domain = $this->createDomain('example.com');
        $this->createProject('project1', 'Project 1', $domain);

        // www.project1.example.com should lose the `www` but keep `project1.`
        $this->setHost('www.project1.example.com');
        $this->client()->request('GET', '/');

        $response = $this->client()->getResponse();
        self::assertSame(301, $response->getStatusCode());
        self::assertSame(
            'http://project1.example.com',
            (string) $response->headers->get('Location'),
            'stripping www must not collapse a legitimate project subdomain with it',
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
        self::assertSame(
            301,
            $response->getStatusCode(),
            'nested subdomains must 301 so the innermost host is the canonical one',
        );
        self::assertSame(
            'http://project1.example.com',
            (string) $response->headers->get('Location'),
            'redirect target must resolve to the project subdomain URL',
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
