<?php

namespace Symfonicat\Service;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Project;
use Symfonicat\Entity\RoutingRule;
use Symfonicat\Entity\Application;
use Symfonicat\Repository\RoutingRuleRepository;

final class RoutingRuleService
{
    public function __construct(
        private readonly PathService $pathService,
        private readonly RoutingRuleRepository $routingRuleRepository,
    ) {
    }

    public function getRedirectRuleForDomain(Domain $domain): ?RoutingRule
    {
        return $this->routingRuleRepository->findOneRedirectRuleForDomain($domain);
    }

    /**
     * @return RoutingRule[]
     */
    public function getTypeDomainByDomain(Domain $domain): array
    {
        return $this->routingRuleRepository->findTypeDomainByDomain($domain);
    }

    public function getTypeDomainByDomainAndPath(Domain $domain, string $path): ?RoutingRule
    {
        foreach ($this->routingRuleRepository->findTypeDomainByDomain($domain) as $rule) {
            if ($this->matchesPath($rule, $path)) {
                return $rule;
            }
        }

        return null;
    }

    public function getRedirectRuleForProject(Project $project): ?RoutingRule
    {
        return $this->routingRuleRepository->findOneRedirectRuleForProject($project);
    }

    /**
     * @return RoutingRule[]
     */
    public function getTypeProjectByProject(Project $project): array
    {
        return $this->routingRuleRepository->findTypeProjectByProject($project);
    }

    public function getTypeProjectByProjectAndPath(Project $project, string $path): ?RoutingRule
    {
        foreach ($this->routingRuleRepository->findTypeProjectByProject($project) as $rule) {
            if ($this->matchesPath($rule, $path)) {
                return $rule;
            }
        }

        return null;
    }

    public function getRouteRuleForDomain(Domain $domain): ?RoutingRule
    {
        return $this->routingRuleRepository->findOneRouteRuleForDomain($domain);
    }

    public function getRouteRuleForProject(Project $project): ?RoutingRule
    {
        return $this->routingRuleRepository->findOneRouteRuleForProject($project);
    }

    public function getApplicationRuleForPath(string $path): ?RoutingRule
    {
        foreach ($this->routingRuleRepository->findTypeApplicationByApplicationType(RoutingRule::APPLICATION_TYPE_ARGUMENTS) as $rule) {
            if ($this->matchesPath($rule, $path, true)) {
                return $rule;
            }
        }

        return null;
    }

    public function getApplicationRuleForRoute(string $route): ?RoutingRule
    {
        $route = trim($route);
        if ($route === '') {
            return null;
        }

        return $this->routingRuleRepository->findOneTypeApplicationByRoute($route);
    }

    public function getApplicationRuleForDomain(Domain $domain): ?RoutingRule
    {
        return $this->routingRuleRepository->findOneTypeApplicationByDomain($domain);
    }

    public function getApplicationRuleForProject(Project $project): ?RoutingRule
    {
        return $this->routingRuleRepository->findOneTypeApplicationByProject($project);
    }

    public function getApplicationRuleForDomainAndProject(Domain $domain, Project $project): ?RoutingRule
    {
        return $this->routingRuleRepository->findOneTypeApplicationByDomainAndProject($domain, $project);
    }

    public function getApplicationRuleForApplication(Application|string $application): ?RoutingRule
    {
        $applicationId = $application instanceof Application ? (string) $application->getId(true) : $application;

        return $this->routingRuleRepository->findOneTypeApplicationByApplicationId($applicationId);
    }

    private function matchesPath(RoutingRule $rule, string $path, bool $allowTrailingPath = false): bool
    {
        foreach ($rule->getArguments() as $argument) {
            if (in_array(strtolower(trim($argument)), RoutingRule::RESERVED_ARGUMENTS, true)) {
                return false;
            }
        }

        return $this->pathService->matchesArguments($rule->getArguments(), $path, $allowTrailingPath);
    }
}
