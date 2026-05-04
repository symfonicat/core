<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatWebTestCase;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\RoutingRule;

/**
 * End-to-end guards for /m/<module>/... routes.
 *
 * Covers two intertwined contracts:
 *
 *   1. AbstractModuleController only lets a module handler run when the
 *      current domain or project has actually installed the module. Calls
 *      without that association return 404 instead of serving module content
 *      — the unit test pins the constructor logic; this test proves the wire
 *      is hooked up end-to-end through routing + DI.
 *   2. The project catch-all route declares `requirements: ['path' => '(?!m(?:/|$)).*']`
 *      so that the generic {path} wildcard never shadows the more specific
 *      /m/* controllers. That regex is the only thing keeping any request for
 *      /m/symfonicat/analytics/main from being absorbed by MainController::main().
 */
final class ModuleGuardTest extends SymfonicatWebTestCase
{
    public function testModuleRouteReturns404WhenModuleNotInstalledOnProject(): void
    {
        $domain = $this->createDomain('example.com');
        $this->createProject('project1', $domain);
        // Module exists in the DB (so ModuleService::load() returns it) but is
        // NOT associated with project1. Guard must refuse to execute.
        $this->createModule('symfonicat/analytics/main');

        $this->setHost('project1.example.com');
        $this->client()->request('POST', '/m/symfonicat/analytics/main');

        self::assertResponseStatusCodeSame(
            404,
            'project that has not installed the module must not be able to invoke it',
        );
    }

    public function testModuleRouteSucceedsWhenModuleInstalledOnProject(): void
    {
        $domain = $this->createDomain('example.com');
        $project = $this->createProject('project1', $domain);
        $module = $this->createModule('symfonicat/analytics/main');
        $project->addModule($module);
        $this->entityManager()->flush();

        $this->setHost('project1.example.com');
        $this->client()->request('POST', '/m/symfonicat/analytics/main');

        self::assertResponseIsSuccessful();
        self::assertJson((string) $this->client()->getResponse()->getContent());
        self::assertStringContainsString(
            '"working":true',
            (string) $this->client()->getResponse()->getContent(),
            'installed module must serve its JSON payload',
        );
    }

    public function testModuleRouteSucceedsWhenModuleInstalledOnApplicationContext(): void
    {
        $this->createDomain('example.com');
        $module = $this->createModule('symfonicat/analytics/main');
        $application = (new Application())->setId('core/test');
        $application->addModule($module);
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplication($application)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_ARGUMENTS)
            ->setArguments(['u', '*', 'x*']);

        $this->entityManager()->persist($application);
        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        $this->setHost('example.com');
        $this->client()->request('GET', '/u/foo/xxx');
        self::assertResponseIsSuccessful('application routing rule should render the test application');
        $token = $this->applicationCsrfTokenFromLastResponse();

        $this->client()->request('POST', '/m/symfonicat/analytics/main', [], [], [
            'HTTP_X_SYMFONICAT_APPLICATION_REQUEST' => '1',
            'HTTP_X_SYMFONICAT_APPLICATION' => 'test',
            'HTTP_X_SYMFONICAT_APPLICATION_TOKEN' => $token,
            'HTTP_ORIGIN' => 'http://example.com',
        ]);

        self::assertResponseIsSuccessful();
        self::assertJson((string) $this->client()->getResponse()->getContent());
        self::assertStringContainsString(
            '"working":true',
            (string) $this->client()->getResponse()->getContent(),
            'application-installed module must serve its JSON payload from a matching application route context',
        );
    }

    public function testModuleRouteRejectsUnknownApplicationContext(): void
    {
        $this->createDomain('example.com');
        $module = $this->createModule('symfonicat/analytics/main');
        $application = (new Application())->setId('core/test');
        $application->addModule($module);
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplication($application)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_ARGUMENTS)
            ->setArguments(['u', '*', 'x*']);

        $this->entityManager()->persist($application);
        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        $this->setHost('example.com');
        $this->client()->request('GET', '/u/foo/xxx');
        self::assertResponseIsSuccessful('application routing rule should render the test application');
        $token = $this->applicationCsrfTokenFromLastResponse();

        $this->client()->request('POST', '/m/symfonicat/analytics/main', [], [], [
            'HTTP_X_SYMFONICAT_APPLICATION_REQUEST' => '1',
            'HTTP_X_SYMFONICAT_APPLICATION' => 'does-not-exist',
            'HTTP_X_SYMFONICAT_APPLICATION_TOKEN' => $token,
            'HTTP_ORIGIN' => 'http://example.com',
        ]);

        self::assertResponseStatusCodeSame(
            404,
            'module request headers must map back to a real application before the module can run',
        );
    }

    public function testModuleRouteRejectsApplicationContextWithoutValidCsrfToken(): void
    {
        $this->createDomain('example.com');
        $module = $this->createModule('symfonicat/analytics/main');
        $application = (new Application())->setId('core/test');
        $application->addModule($module);
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplication($application)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_ARGUMENTS)
            ->setArguments(['u', '*', 'x*']);

        $this->entityManager()->persist($application);
        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        $this->setHost('example.com');
        $this->client()->request('POST', '/m/symfonicat/analytics/main', [], [], [
            'HTTP_X_SYMFONICAT_APPLICATION_REQUEST' => '1',
            'HTTP_X_SYMFONICAT_APPLICATION' => 'test',
            'HTTP_X_SYMFONICAT_APPLICATION_TOKEN' => 'invalid',
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testModuleRouteRespectsHttpMethodWhitelist(): void
    {
        $domain = $this->createDomain('example.com');
        $project = $this->createProject('project1', $domain);
        $module = $this->createModule('symfonicat/analytics/main');
        $project->addModule($module);
        $this->entityManager()->flush();

        $this->setHost('project1.example.com');
        $this->client()->request('GET', '/m/symfonicat/analytics/main');

        // Analytics controller is declared POST-only. GET must 405 instead of
        // being swallowed by the project catch-all (which would return 200).
        $status = $this->client()->getResponse()->getStatusCode();
        self::assertContains(
            $status,
            [404, 405],
            sprintf('GET /m/symfonicat/analytics/main must not render the project shell; got %d', $status),
        );
        self::assertStringNotContainsString(
            'core/project1',
            (string) $this->client()->getResponse()->getContent(),
            'the project catch-all (which emits "core/project1") must not absorb /m/* requests',
        );
    }

    public function testProjectCatchAllIgnoresPathsThatStartWithM(): void
    {
        // Bare "m" and "m/something" must not be consumed by the project
        // catch-all requirement `(?!m(?:/|$)).*`.
        $domain = $this->createDomain('example.com');
        $this->createProject('project1', $domain);

        $this->setHost('project1.example.com');

        // /m (no trailing slash, no module): nothing to serve; not the shell.
        $this->client()->request('GET', '/m');
        self::assertNotSame(
            200,
            $this->client()->getResponse()->getStatusCode(),
            'bare /m must not resolve to the project shell',
        );

        // /m/does-not-exist: module controller space, but nothing registered.
        $this->client()->request('POST', '/m/does-not-exist');
        self::assertSame(
            404,
            $this->client()->getResponse()->getStatusCode(),
            '/m/<unknown> must 404, not leak to the project catch-all',
        );

        // /milestones: "m" followed by letters is not a module prefix and the
        // catch-all MUST claim it (the regex is `(?!m(?:/|$))`, i.e. only
        // reject when "m" is followed by "/" or end-of-string).
        $this->client()->request('GET', '/milestones');
        self::assertResponseIsSuccessful(
            '/milestones is a normal path segment and must render the project shell',
        );
    }

    private function applicationCsrfTokenFromLastResponse(): string
    {
        $content = (string) $this->client()->getResponse()->getContent();
        preg_match('/"csrfToken"\s*:\s*"([^"]+)"/', $content, $matches);

        self::assertArrayHasKey(1, $matches, 'application helper should emit a module CSRF token');
        self::assertNotSame('csrf-token', $matches[1]);

        return $matches[1];
    }
}
