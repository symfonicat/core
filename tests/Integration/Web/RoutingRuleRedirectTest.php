<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatWebTestCase;
use Symfonicat\Entity\RoutingRule;

final class RoutingRuleRedirectTest extends SymfonicatWebTestCase
{
    public function testDomainRedirectCanTargetProjectOnSpecificDomain(): void
    {
        $sourceDomain = $this->createDomain('example.com');
        $targetDomain = $this->createDomain('other.example');
        $targetProject = $this->createProject('project2', $targetDomain);

        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_REDIRECT)
            ->setRedirectType(RoutingRule::REDIRECT_TYPE_DOMAIN)
            ->setDomain($sourceDomain)
            ->setRedirectTarget(RoutingRule::REDIRECT_TYPE_DOMAIN_PROJECT)
            ->setRedirectDomain($targetDomain)
            ->setRedirectProject($targetProject);

        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        $this->setHost('example.com');
        $this->client()->request('GET', '/docs');

        self::assertResponseRedirects(
            'http://project2.other.example/docs',
            302,
            'combined redirect target must resolve to project.id + "." + domain.id',
        );
    }
}
