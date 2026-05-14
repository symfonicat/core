<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatWebTestCase;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\RoutingRule;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class ApplicationRouteTest extends SymfonicatWebTestCase
{
    public function testSymfonyPathGeneratesApplicationRulePath(): void
    {
        $this->seedApplicationRule();

        /** @var UrlGeneratorInterface $router */
        $router = self::getTestContainer()->get('router');

        self::assertSame('/symfonicat/*/test', $router->generate('symfonicat_application', [
            'id' => 'test',
        ]));
        self::assertSame('/symfonicat/*/test', $router->generate('symfonicat_application', [
            'vendor' => 'core',
            'id' => 'test',
        ]));
        self::assertSame('/symfonicat/*/test/somepath/path2', $router->generate('symfonicat_application', [
            'id' => 'test',
            'path' => 'somepath/path2',
        ]));
        self::assertSame('/symfonicat/*/test/somepath/path2', $router->generate('symfonicat_application', [
            'vendor' => 'core',
            'id' => 'test',
            'path' => 'somepath/path2',
        ]));
    }

    public function testTwigApplicationPathFunctionMatchesReadmeExamples(): void
    {
        $application = $this->seedApplicationRule();

        /** @var Environment $twig */
        $twig = self::getTestContainer()->get(Environment::class);

        self::assertSame('/symfonicat/*/test', trim($twig->createTemplate('{{ path_application("test") }}')->render()));
        self::assertSame('/symfonicat/*/test', trim($twig->createTemplate('{{ path_application("core/test") }}')->render()));
        self::assertSame('/symfonicat/*/test', trim($twig->createTemplate('{{ path_application(application) }}')->render([
            'application' => $application,
        ])));
        self::assertSame('/symfonicat/tay/test', trim($twig->createTemplate('{{ path_application(application, { user: "tay" }) }}')->render([
            'application' => $application,
        ])));
        self::assertSame('/symfonicat/*/test/somepath/testpath', trim($twig->createTemplate('{{ path_application(application, "somepath/testpath") }}')->render([
            'application' => $application,
        ])));
        self::assertSame('/symfonicat/tay/test/somepath', trim($twig->createTemplate('{{ path_application("core/test", "somepath", { user: "tay" }) }}')->render()));
        self::assertSame('/symfonicat/tay/test/somepath', trim($twig->createTemplate('{{ path_application("core/test", { user: "tay" }, "somepath") }}')->render()));
        self::assertSame('/symfonicat/tay/test', trim($twig->createTemplate('{{ path_application(application, ["tay"]) }}')->render([
            'application' => $application,
        ])));
        self::assertSame('/symfonicat/tay/test/somepath/testpath', trim($twig->createTemplate('{{ path_application(application, "somepath/testpath", ["tay"]) }}')->render([
            'application' => $application,
        ])));
        self::assertSame('/symfonicat/*/test', trim($twig->createTemplate('{{ path("symfonicat_application", {id: "test"}) }}')->render()));
        self::assertSame('/symfonicat/*/test', trim($twig->createTemplate('{{ path("symfonicat_application", {vendor: "core", id: "test"}) }}')->render()));
    }

    public function testRouteBasedApplicationRuleUsesConfiguredSymfonyRoute(): void
    {
        $application = (new Application())->setId('core/test');
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplication($application)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_ROUTE)
            ->setRoute('symfonicat_project_test');

        $this->entityManager()->persist($application);
        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        /** @var UrlGeneratorInterface $router */
        $router = self::getTestContainer()->get('router');
        /** @var Environment $twig */
        $twig = self::getTestContainer()->get(Environment::class);

        self::assertSame('/test', $router->generate('symfonicat_application', ['id' => 'test']));
        self::assertSame('/test', trim($twig->createTemplate('{{ path_application("test") }}')->render()));
        self::assertSame('/test', trim($twig->createTemplate('{{ path_application(application) }}')->render([
            'application' => $application,
        ])));
    }

    public function testInternalApplicationRouteRendersShellWithPublicApplicationContext(): void
    {
        $this->seedApplicationRule();

        $this->setHost('example.com');
        $this->client()->request('GET', '/application/core/test/somepath/path2');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client()->getResponse()->getContent();

        self::assertMatchesRegularExpression('/"id"\s*:\s*"core\/test"/', $content);
        self::assertStringNotContainsString('"path":', $content);
        self::assertStringContainsString('"redirectTo": "/symfonicat/*/test/somepath/path2"', $content);
        self::assertMatchesRegularExpression('/"csrfToken"\s*:\s*"[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+"/', $content);
        self::assertStringNotContainsString('"csrfToken": "csrf-token"', $content);
    }

    public function testGeneratedApplicationPathRendersApplicationShell(): void
    {
        $this->seedApplicationRule();

        $this->setHost('example.com');
        $this->client()->request('GET', '/symfonicat/pizza/test/somepath/path2');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            'test',
            (string) $this->client()->getResponse()->getContent(),
            'application routing rules should match generated app URLs with trailing paths',
        );
    }

    public function testRouteBasedApplicationRuleInjectsApplicationIntoController(): void
    {
        $application = (new Application())->setId('core/test');
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplication($application)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_ROUTE)
            ->setRoute('symfonicat_project_test');

        $this->entityManager()->persist($application);
        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        $this->setHost('example.com');
        $this->client()->request('GET', '/test');

        self::assertResponseIsSuccessful();
        self::assertSame('test core/test', (string) $this->client()->getResponse()->getContent());
    }

    public function testDomainApplicationRuleRendersApplicationShellOnBareDomain(): void
    {
        $domain = $this->createDomain('example.com');
        $application = (new Application())->setId('core/test');
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplication($application)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_DOMAIN)
            ->setDomain($domain);

        $this->entityManager()->persist($application);
        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        $this->setHost('example.com');
        $this->client()->request('GET', '/docs');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client()->getResponse()->getContent();

        self::assertMatchesRegularExpression('/"id"\s*:\s*"core\/test"/', $content);
        self::assertStringContainsString('test', $content);
    }

    public function testProjectApplicationRuleRendersApplicationShellOnProjectSubdomain(): void
    {
        $domain = $this->createDomain('example.com');
        $project = $this->createProject('project1', $domain);
        $application = (new Application())->setId('core/test');
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplication($application)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_PROJECT)
            ->setProject($project);

        $this->entityManager()->persist($application);
        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        $this->setHost('project1.example.com');
        $this->client()->request('GET', '/docs');

        self::assertResponseIsSuccessful();
        self::assertMatchesRegularExpression('/"id"\s*:\s*"core\/test"/', (string) $this->client()->getResponse()->getContent());
    }

    public function testDomainProjectApplicationRuleRendersApplicationShellForExactPair(): void
    {
        $domain = $this->createDomain('example.com');
        $project = $this->createProject('project1', $domain);
        $application = (new Application())->setId('core/test');
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplication($application)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_DOMAIN_PROJECT)
            ->setDomain($domain)
            ->setProject($project);

        $this->entityManager()->persist($application);
        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        $this->setHost('project1.example.com');
        $this->client()->request('GET', '/docs');

        self::assertResponseIsSuccessful();
        self::assertMatchesRegularExpression('/"id"\s*:\s*"core\/test"/', (string) $this->client()->getResponse()->getContent());
    }

    public function testProjectRouteRuleOverridesProjectApplicationBinding(): void
    {
        $domain = $this->createDomain('example.com');
        $project = $this->createProject('project1', $domain);
        $application = (new Application())->setId('core/test');
        $applicationRule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplication($application)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_PROJECT)
            ->setProject($project);
        $routeRule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_ROUTE)
            ->setRouteType(RoutingRule::ROUTE_TYPE_PROJECT)
            ->setProject($project)
            ->setRoute('symfonicat_project_test');

        $this->entityManager()->persist($application);
        $this->entityManager()->persist($applicationRule);
        $this->entityManager()->persist($routeRule);
        $this->entityManager()->flush();

        $this->setHost('project1.example.com');
        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame('test', (string) $this->client()->getResponse()->getContent());
    }

    private function seedApplicationRule(): Application
    {
        $application = (new Application())->setId('core/test');
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplication($application)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_ARGUMENTS)
            ->setArguments(['symfonicat', '*', 'test*']);

        $this->entityManager()->persist($application);
        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        return $application;
    }
}
