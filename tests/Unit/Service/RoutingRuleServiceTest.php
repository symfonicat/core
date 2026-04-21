<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Project;
use Symfonicat\Entity\RoutingRule;
use Symfonicat\Repository\RoutingRuleRepository;
use Symfonicat\Service\PathService;
use Symfonicat\Service\RoutingRuleService;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Guarantees the path matching checks the RoutingRuleSubscriber relies on.
 */
final class RoutingRuleServiceTest extends TestCase
{
    public function testDomainLookupIgnoresReservedAdminArguments(): void
    {
        $domain = (new Domain())->setId('example.com');
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_DOMAIN)
            ->setDomain($domain)
            ->setArguments(['admin']);

        $repo = $this->createMock(RoutingRuleRepository::class);
        $repo->expects(self::once())
            ->method('findTypeDomainByDomain')
            ->with(self::identicalTo($domain))
            ->willReturn([$rule]);

        $service = $this->service($repo);

        self::assertNull($service->getTypeDomainByDomainAndPath($domain, '/admin'));
    }

    public function testProjectLookupIgnoresReservedAdminArguments(): void
    {
        $project = (new Project())->setId('project1')->setName('Project 1');
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_PROJECT)
            ->setProject($project)
            ->setArguments(['ADMIN']);

        $repo = $this->createMock(RoutingRuleRepository::class);
        $repo->expects(self::once())
            ->method('findTypeProjectByProject')
            ->with(self::identicalTo($project))
            ->willReturn([$rule]);

        $service = $this->service($repo);

        self::assertNull($service->getTypeProjectByProjectAndPath($project, '/admin'));
    }

    public function testDomainLookupReturnsFirstMatchingPathRule(): void
    {
        $domain = (new Domain())->setId('example.com');
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_DOMAIN)
            ->setDomain($domain)
            ->setArguments(['blog']);

        $repo = $this->createMock(RoutingRuleRepository::class);
        $repo->expects(self::once())
            ->method('findTypeDomainByDomain')
            ->with(self::identicalTo($domain))
            ->willReturn([$rule]);

        $service = $this->service($repo);

        self::assertSame($rule, $service->getTypeDomainByDomainAndPath($domain, '/blog'));
    }

    public function testProjectLookupReturnsFirstMatchingPathRule(): void
    {
        $project = (new Project())->setId('project1')->setName('Project 1');
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_PROJECT)
            ->setProject($project)
            ->setArguments(['blog']);

        $repo = $this->createMock(RoutingRuleRepository::class);
        $repo->expects(self::once())
            ->method('findTypeProjectByProject')
            ->with(self::identicalTo($project))
            ->willReturn([$rule]);

        $service = $this->service($repo);

        self::assertSame($rule, $service->getTypeProjectByProjectAndPath($project, '/blog'));
    }

    public function testCollectionLookupsAreStraightPassThrough(): void
    {
        $domain = (new Domain())->setId('example.com');
        $project = (new Project())->setId('project1')->setName('Project 1');

        $domainRules = [new RoutingRule(), new RoutingRule()];
        $projectRules = [new RoutingRule()];

        $repo = $this->createMock(RoutingRuleRepository::class);
        $repo->expects(self::once())
            ->method('findTypeDomainByDomain')
            ->with(self::identicalTo($domain))
            ->willReturn($domainRules);
        $repo->expects(self::once())
            ->method('findTypeProjectByProject')
            ->with(self::identicalTo($project))
            ->willReturn($projectRules);

        $service = $this->service($repo);

        self::assertSame($domainRules, $service->getTypeDomainByDomain($domain));
        self::assertSame($projectRules, $service->getTypeProjectByProject($project));
    }

    private function service(RoutingRuleRepository $repository): RoutingRuleService
    {
        return new RoutingRuleService(new PathService(new RequestStack()), $repository);
    }
}
