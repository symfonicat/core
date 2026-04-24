<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
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
    #[DataProvider('reservedArgumentProvider')]
    public function testDomainLookupIgnoresReservedArguments(string $argument, string $path): void
    {
        $domain = (new Domain())->setId('example.com');
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_DOMAIN)
            ->setDomain($domain)
            ->setArguments([$argument]);

        $repo = $this->createMock(RoutingRuleRepository::class);
        $repo->expects(self::once())
            ->method('findTypeDomainByDomain')
            ->with(self::identicalTo($domain))
            ->willReturn([$rule]);

        $service = $this->service($repo);

        self::assertNull($service->getTypeDomainByDomainAndPath($domain, $path));
    }

    #[DataProvider('reservedArgumentProvider')]
    public function testProjectLookupIgnoresReservedArguments(string $argument, string $path): void
    {
        $project = (new Project())->setId('project1')->setName('Project 1');
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_PROJECT)
            ->setProject($project)
            ->setArguments([$argument]);

        $repo = $this->createMock(RoutingRuleRepository::class);
        $repo->expects(self::once())
            ->method('findTypeProjectByProject')
            ->with(self::identicalTo($project))
            ->willReturn([$rule]);

        $service = $this->service($repo);

        self::assertNull($service->getTypeProjectByProjectAndPath($project, $path));
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

    public function testApplicationRouteLookupPassesThroughToRepository(): void
    {
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_ROUTE)
            ->setRoute('app_project_test');

        $repo = $this->createMock(RoutingRuleRepository::class);
        $repo->expects(self::once())
            ->method('findOneTypeApplicationByRoute')
            ->with('app_project_test')
            ->willReturn($rule);

        $service = $this->service($repo);

        self::assertSame($rule, $service->getApplicationRuleForRoute('app_project_test'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function reservedArgumentProvider(): iterable
    {
        yield 'admin' => ['admin', '/admin'];
        yield 'module' => ['m', '/m'];
        yield 'application' => ['application', '/application'];
    }

    private function service(RoutingRuleRepository $repository): RoutingRuleService
    {
        return new RoutingRuleService(new PathService(new RequestStack()), $repository);
    }
}
