<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\RoutingRule;
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

        self::assertStringContainsString(
            '<td>docs</td>',
            $this->renderRow($rule),
        );
    }

    public function testRowRendersMissingApplicationRuleWithoutNullAccess(): void
    {
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setArguments(['symfonicat', '*', 'test*']);

        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        self::assertStringContainsString(
            'application:',
            $this->renderRow($rule),
        );
    }

    public function testRowLinksApplicationRuleWhenApplicationExists(): void
    {
        $application = (new Application())->setId('test');
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplication($application)
            ->setArguments(['symfonicat', '*', 'test*']);

        $this->entityManager()->persist($application);
        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        self::assertStringContainsString(
            'href="/symfonicat/*/test"',
            $this->renderRow($rule),
        );
    }

    private function renderRow(RoutingRule $rule): string
    {
        /** @var Environment $twig */
        $twig = self::getTestContainer()->get(Environment::class);

        return $twig->render('admin/routing_rule/_row.html.twig', [
            'rule' => $rule,
        ]);
    }
}
