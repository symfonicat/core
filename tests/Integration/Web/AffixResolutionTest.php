<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatWebTestCase;

/**
 * Exercises the ProjectSubscriber's host-dispatching logic end-to-end.
 *
 * This is the behavior that makes Symfonicat "one app, many shells": hitting
 * example.com renders the domain shell, hitting subdomain1.example.com renders
 * the subdomain shell, and all the redirects in between do the right thing.
 */
final class AffixResolutionTest extends SymfonicatWebTestCase
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

    public function testProjectAffixRendersProjectShellWhenProjectIsAttachedToDomain(): void
    {
        $domain = $this->createDomain('example.com');
        $subdomain = $this->createProject('subdomain1', $domain);

        $this->setHost('subdomain1.example.com');
        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'core/subdomain1');
    }

    public function testProjectEnvOverlaysDomainEnvInProjectShell(): void
    {
        $domain = $this->createDomain('example.com');
        $subdomain = $this->createProject('subdomain1', $domain);
        $color = $this->createEnv('primary');
        $this->setDomainEnv($domain, $color, 'blue');
        $this->setProjectEnv($subdomain, $color, 'green');

        $this->setHost('subdomain1.example.com');
        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'color: green', 'subdomain env must win over domain env on the subdomain shell');
    }

    public function testAffixForUnknownProjectRedirectsToBareDomain(): void
    {
        $this->createDomain('example.com');

        $this->setHost('unknown.example.com');
        $this->client()->request('GET', '/');

        $response = $this->client()->getResponse();
        self::assertSame(
            301,
            $response->getStatusCode(),
            'unknown subdomain affixs must 301 so search engines canonicalize on the bare domain',
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

    public function testWwwPrefixIsStrippedButInnerAffixSurvives(): void
    {
        $domain = $this->createDomain('example.com');
        $this->createProject('subdomain1', $domain);

        // www.subdomain1.example.com should lose the `www` but keep `subdomain1.`
        $this->setHost('www.subdomain1.example.com');
        $this->client()->request('GET', '/');

        $response = $this->client()->getResponse();
        self::assertSame(301, $response->getStatusCode());
        self::assertSame(
            'http://subdomain1.example.com',
            (string) $response->headers->get('Location'),
            'stripping www must not collapse a legitimate subdomain affix with it',
        );
    }

    public function testDeepAffixFoldsDownToTheInnermostProject(): void
    {
        $domain = $this->createDomain('example.com');
        $this->createProject('subdomain1', $domain);

        // Two affix segments: foo.subdomain1.example.com should fold to
        // subdomain1.example.com (the innermost affix wins).
        $this->setHost('foo.subdomain1.example.com');
        $this->client()->request('GET', '/');

        $response = $this->client()->getResponse();
        self::assertSame(
            301,
            $response->getStatusCode(),
            'nested affixs must 301 so the innermost host is the canonical one',
        );
        self::assertSame(
            'http://subdomain1.example.com',
            (string) $response->headers->get('Location'),
            'redirect target must resolve to the subdomain affix URL',
        );
    }

    public function testAdminPathIsImmuneToAffixRedirects(): void
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
            sprintf('/admin paths must not be redirected by the affix subscriber (got %d)', $status),
        );
    }
}
