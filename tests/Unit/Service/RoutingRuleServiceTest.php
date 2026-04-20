<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Project;
use Symfonicat\Entity\RoutingRule;
use Symfonicat\Repository\RoutingRuleRepository;
use Symfonicat\Service\RoutingRuleService;

/**
 * Guarantees the two reservation checks the RoutingRuleSubscriber relies on:
 *   - "admin" is never resolved from the repository, regardless of what rows
 *     somebody (mistakenly) typed into the database via the admin UI.
 *   - Non-reserved arguments forward through to the repository verbatim.
 */
final class RoutingRuleServiceTest extends TestCase
{
    public function testDomainLookupSkipsRepositoryForReservedAdminArgument(): void
    {
        $repo = $this->createMock(RoutingRuleRepository::class);
        $repo->expects(self::never())->method('findOneTypeDomainByDomainAndArgument');

        $service = new RoutingRuleService($repo);
        $domain = (new Domain())->setId('example.com');

        self::assertNull($service->getTypeDomainByDomainAndArgument($domain, 'admin'));
        self::assertNull($service->getTypeDomainByDomainAndArgument($domain, 'ADMIN'));
        self::assertNull($service->getTypeDomainByDomainAndArgument($domain, '  admin '));
    }

    public function testProjectLookupSkipsRepositoryForReservedAdminArgument(): void
    {
        $repo = $this->createMock(RoutingRuleRepository::class);
        $repo->expects(self::never())->method('findOneTypeProjectByProjectAndArgument');

        $service = new RoutingRuleService($repo);
        $project = (new Project())->setId('project1')->setName('Project 1');

        self::assertNull($service->getTypeProjectByProjectAndArgument($project, 'admin'));
    }

    public function testDomainLookupDelegatesToRepositoryForNonReservedArgument(): void
    {
        $domain = (new Domain())->setId('example.com');
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_DOMAIN)
            ->setDomain($domain)
            ->setArgument('blog');

        $repo = $this->createMock(RoutingRuleRepository::class);
        $repo->expects(self::once())
            ->method('findOneTypeDomainByDomainAndArgument')
            ->with(self::identicalTo($domain), 'blog')
            ->willReturn($rule);

        $service = new RoutingRuleService($repo);

        self::assertSame($rule, $service->getTypeDomainByDomainAndArgument($domain, 'blog'));
    }

    public function testProjectLookupDelegatesToRepositoryForNonReservedArgument(): void
    {
        $project = (new Project())->setId('project1')->setName('Project 1');
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_PROJECT)
            ->setProject($project)
            ->setArgument('blog');

        $repo = $this->createMock(RoutingRuleRepository::class);
        $repo->expects(self::once())
            ->method('findOneTypeProjectByProjectAndArgument')
            ->with(self::identicalTo($project), 'blog')
            ->willReturn($rule);

        $service = new RoutingRuleService($repo);

        self::assertSame($rule, $service->getTypeProjectByProjectAndArgument($project, 'blog'));
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

        $service = new RoutingRuleService($repo);

        self::assertSame($domainRules, $service->getTypeDomainByDomain($domain));
        self::assertSame($projectRules, $service->getTypeProjectByProject($project));
    }
}
