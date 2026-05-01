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
        self::assertSame('/symfonicat/*/test/somepath/path2', $router->generate('symfonicat_application', [
            'id' => 'test',
            'path' => 'somepath/path2',
        ]));
    }

    public function testTwigApplicationPathFunctionGeneratesApplicationRulePath(): void
    {
        $application = $this->seedApplicationRule();

        /** @var Environment $twig */
        $twig = self::getTestContainer()->get(Environment::class);

        self::assertSame('/symfonicat/*/test', trim($twig->createTemplate('{{ path_application("test") }}')->render()));
        self::assertSame('/symfonicat/*/test', trim($twig->createTemplate('{{ path_application(application) }}')->render([
            'application' => $application,
        ])));
        self::assertSame('/symfonicat/*/test/somepath/path2', trim($twig->createTemplate('{{ path_application("test", "somepath/path2") }}')->render()));
        self::assertSame('/symfonicat/*/test/somepath/path2', trim($twig->createTemplate('{{ path_application(application, "somepath/path2") }}')->render([
            'application' => $application,
        ])));
        self::assertSame('/symfonicat/tay/test', trim($twig->createTemplate('{{ path_application("test", ["tay"]) }}')->render()));
        self::assertSame('/symfonicat/tay/test', trim($twig->createTemplate('{{ path_application(application, ["tay"]) }}')->render([
            'application' => $application,
        ])));
        self::assertSame('/symfonicat/tay/test/somepath/path2', trim($twig->createTemplate('{{ path_application("test", "somepath/path2", ["tay"]) }}')->render()));
        self::assertSame('/symfonicat/tay/test/somepath/path2', trim($twig->createTemplate('{{ path_application(application, "somepath/path2", ["tay"]) }}')->render([
            'application' => $application,
        ])));
        self::assertSame('/symfonicat/*/test', trim($twig->createTemplate('{{ path("symfonicat_application", {id: "test"}) }}')->render()));
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
        $this->client()->request('GET', '/application/test/somepath/path2');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client()->getResponse()->getContent();

        self::assertMatchesRegularExpression('/"id"\s*:\s*"test"/', $content);
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
        self::assertSame('test test', (string) $this->client()->getResponse()->getContent());
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
