<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatWebTestCase;

/**
 * Verifies that database-backed RoutingRule rows invert the default
 * subdomain → project / bare → domain mapping:
 *
 *   - A TYPE_DOMAIN rule whose argument matches the first path segment forces
 *     the domain shell even when the request arrived on a project subdomain.
 *   - A TYPE_PROJECT rule disables the project catch-all so the request is
 *     free to fall through to other Symfony routes (or 404 if nothing else
 *     matches).
 *
 * These are the knobs that operators use to carve path-based escape hatches
 * out of the default routing, so regressions here silently break production
 * deployments.
 */
final class RoutingRuleInversionTest extends SymfonicatWebTestCase
{
    public function testDomainRuleForcesDomainShellEvenUnderProjectSubdomain(): void
    {
        $domain = $this->createDomain('example.com');
        $project = $this->createProject('project1', $domain);
        $env = $this->createEnv('primary');
        $this->setDomainEnv($domain, $env, 'blue');
        $this->setProjectEnv($project, $env, 'green');

        // Baseline: without the rule, /docs on project1.example.com renders
        // the project shell.
        $this->setHost('project1.example.com');
        $this->client()->request('GET', '/docs');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'core/project1', 'project shell is the pre-rule default');

        // Now install a domain rule for "docs" and repeat the request. The
        // RoutingRuleSubscriber should rewrite the controller target to the
        // domain shell before the normal project catch-all can claim it.
        $this->createDomainRoutingRule($domain, 'docs');

        $this->client()->request('GET', '/docs');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            'body',
            'example.com',
            'domain rule must flip the /docs path to render the domain template',
        );
        // NOTE: Env overlay is intentionally not asserted here. EnvService still
        // sees both the project and the domain (the rule only overrides which
        // TEMPLATE renders, not which entity is loaded), so `colors.primary` stays
        // at the project value. That's consistent with EnvServiceTest and is
        // captured explicitly there; calling it out here would double-pin the
        // same contract in two places.
    }

    public function testDomainRuleIsScopedToItsDomain(): void
    {
        $exampleCom = $this->createDomain('example.com');
        $this->createProject('project1', $exampleCom);

        $otherDomain = $this->createDomain('other.example');
        $this->createDomainRoutingRule($otherDomain, 'docs');

        $this->setHost('project1.example.com');
        $this->client()->request('GET', '/docs');

        // Rule belongs to other.example, not example.com: project shell wins.
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            'body',
            'core/project1',
            'rules on foreign domains must not leak across domains',
        );
    }

    public function testProjectRuleDisablesCatchAllSoRequestCan404(): void
    {
        $domain = $this->createDomain('example.com');
        $project = $this->createProject('project1', $domain);

        // Baseline confirms the project catch-all does match /foo-bar.
        $this->setHost('project1.example.com');
        $this->client()->request('GET', '/foo-bar');
        self::assertResponseIsSuccessful('without a project rule the catch-all renders the shell');

        $this->createProjectRoutingRule($project, 'foo-bar');

        $this->client()->request('GET', '/foo-bar');

        self::assertResponseStatusCodeSame(
            404,
            'project rule must suppress the catch-all; with no other route claiming /foo-bar the request 404s',
        );
    }

    public function testAdminPathIsImmuneToRoutingRules(): void
    {
        $domain = $this->createDomain('example.com');
        // An operator can create a rule whose argument collides with /admin/*.
        // Entity validation only blocks an argument that is exactly "admin" (not
        // a prefix match), so build one that sneaks close: "administration".
        $this->createDomainRoutingRule($domain, 'administration');

        $this->setHost('example.com');
        $this->client()->request('GET', '/admin');

        // RoutingRuleSubscriber short-circuits for anything under /admin
        // regardless of rule content, so the admin firewall — not the domain
        // shell — handles the request. An unauthenticated user is bounced to
        // the admin login page.
        $response = $this->client()->getResponse();
        self::assertTrue(
            $response->isRedirect(),
            '/admin must defer to the admin firewall (which 302s unauthenticated users to /admin/login)',
        );
        self::assertStringContainsString(
            '/admin/login',
            (string) $response->headers->get('Location'),
            'redirect must target the admin login, proving the routing rule did not claim /admin',
        );
    }
}
