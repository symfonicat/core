<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\RoutingRule;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Twig\Environment;

final class RoutingRuleAdminRowTest extends SymfonicatKernelTestCase
{
    public function testRowRendersNonApplicationRuleWithoutApplicationLink(): void
    {
        $domain = $this->createDomain('example.com');
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_DOMAIN)
            ->setDomain($domain)
            ->setArguments(['docs']);

        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        $html = $this->renderRow($rule);

        self::assertStringContainsString('/docs', $html);
        self::assertStringNotContainsString('href="/docs"', $html);
    }

    public function testRowRendersMissingApplicationRuleWithoutNullAccess(): void
    {
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_ARGUMENTS)
            ->setArguments(['symfonicat', '*', 'test*']);

        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        $html = $this->renderRow($rule);

        self::assertStringContainsString('<td class="fw-semibold text-nowrap">application</td>', $html);
        self::assertStringContainsString('<code>/symfonicat/*/test*</code>', $html);
        self::assertStringContainsString('arguments', $html);
        self::assertStringNotContainsString('application:', $html);
    }

    public function testRowLinksApplicationRuleWhenApplicationExists(): void
    {
        $application = (new Application())->setId('test');
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplication($application)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_ARGUMENTS)
            ->setArguments(['symfonicat', '*', 'test*']);

        $this->entityManager()->persist($application);
        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        self::assertStringContainsString(
            'href="/symfonicat/*/test"',
            $this->renderRow($rule),
        );
    }

    public function testRowLinksRouteBasedApplicationRuleWhenApplicationExists(): void
    {
        $application = (new Application())->setId('test');
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplication($application)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_ROUTE)
            ->setRoute('symfonicat_project_test');

        $this->entityManager()->persist($application);
        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        $html = $this->renderRow($rule);

        self::assertStringContainsString('href="/test"', $html);
        self::assertStringContainsString('<a href="/test"><code>symfonicat_project_test</code></a>', $html);
        self::assertStringContainsString('<a href="/test"><code>test</code></a>', $html);
        self::assertStringContainsString('route', $html);
        self::assertStringNotContainsString('route: symfonicat_project_test', $html);
    }

    private function renderRow(RoutingRule $rule): string
    {
        $requestStack = self::getTestContainer()->get(RequestStack::class);
        $request = Request::create('/admin/r/list');

        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getTestContainer()->get('session.factory');
        $request->setSession($sessionFactory->createSession());
        $requestStack->push($request);

        /** @var Environment $twig */
        $twig = self::getTestContainer()->get(Environment::class);

        try {
            return $twig->render('admin/routing_rule/_row.html.twig', [
                'rule' => $rule,
            ]);
        } finally {
            $requestStack->pop();
        }
    }
}
