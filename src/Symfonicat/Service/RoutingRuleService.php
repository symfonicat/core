<?php

namespace Symfonicat\Service;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Project;
use Symfonicat\Entity\RoutingRule;
use Symfonicat\Repository\RoutingRuleRepository;

final class RoutingRuleService
{
    public function __construct(
        private readonly RoutingRuleRepository $routingRuleRepository,
    ) {
    }

    /**
     * @return RoutingRule[]
     */
    public function getTypeDomainByDomain(Domain $domain): array
    {
        return $this->routingRuleRepository->findTypeDomainByDomain($domain);
    }

    public function getTypeDomainByDomainAndArgument(Domain $domain, string $argument): ?RoutingRule
    {
        return $this->routingRuleRepository->findOneTypeDomainByDomainAndArgument($domain, $argument);
    }

    /**
     * @return RoutingRule[]
     */
    public function getTypeProjectByProject(Project $project): array
    {
        return $this->routingRuleRepository->findTypeProjectByProject($project);
    }

    public function getTypeProjectByProjectAndArgument(Project $project, string $argument): ?RoutingRule
    {
        return $this->routingRuleRepository->findOneTypeProjectByProjectAndArgument($project, $argument);
    }
}
