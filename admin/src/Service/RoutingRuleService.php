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
        private readonly RuntimeConfig $runtimeConfig,
    ) {
    }

    public function getRedirectRuleForDomain(Domain $domain): ?RoutingRule
    {
        if ($this->usesDatabaseRuntime()) {
            return $this->routingRuleRepository->findOneRedirectRuleForDomain($domain);
        }

        return $this->runtimeConfig->redirectRuleForDomain($domain);
    }

    /**
     * @return RoutingRule[]
     */
    public function getTypeDomainByDomain(Domain $domain): array
    {
        return $this->usesDatabaseRuntime() ? $this->routingRuleRepository->findTypeDomainByDomain($domain) : $this->runtimeConfig->domainRules($domain);
    }

    public function getTypeDomainByDomainAndPath(Domain $domain, string $path): ?RoutingRule
    {
        foreach ($this->getTypeDomainByDomain($domain) as $rule) {
            if ($this->matchesPath($rule, $path)) {
                return $rule;
            }
        }

        return null;
    }

    public function getRedirectRuleForProject(Project $project): ?RoutingRule
    {
        return $this->usesDatabaseRuntime() ? $this->routingRuleRepository->findOneRedirectRuleForProject($project) : $this->runtimeConfig->redirectRuleForProject($project);
    }

    /**
     * @return RoutingRule[]
     */
    public function getTypeProjectByProject(Project $project): array
    {
        return $this->usesDatabaseRuntime() ? $this->routingRuleRepository->findTypeProjectByProject($project) : $this->runtimeConfig->projectRules($project);
    }

    public function getTypeProjectByProjectAndPath(Project $project, string $path): ?RoutingRule
    {
        foreach ($this->getTypeProjectByProject($project) as $rule) {
            if ($this->matchesPath($rule, $path)) {
                return $rule;
            }
        }

        return null;
    }

    public function getRouteRuleForDomain(Domain $domain): ?RoutingRule
    {
        return $this->usesDatabaseRuntime() ? $this->routingRuleRepository->findOneRouteRuleForDomain($domain) : $this->runtimeConfig->routeRuleForDomain($domain);
    }

    public function getRouteRuleForProject(Project $project): ?RoutingRule
    {
        return $this->usesDatabaseRuntime() ? $this->routingRuleRepository->findOneRouteRuleForProject($project) : $this->runtimeConfig->routeRuleForProject($project);
    }

    public function getApplicationRuleForPath(string $path): ?RoutingRule
    {
        $rules = $this->usesDatabaseRuntime()
            ? $this->routingRuleRepository->findTypeApplicationByApplicationType(RoutingRule::APPLICATION_TYPE_ARGUMENTS)
            : $this->runtimeConfig->applicationArgumentRules();

        foreach ($rules as $rule) {
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

        return $this->usesDatabaseRuntime() ? $this->routingRuleRepository->findOneTypeApplicationByRoute($route) : $this->runtimeConfig->applicationRuleByRoute($route);
    }

    public function getApplicationRuleForDomain(Domain $domain): ?RoutingRule
    {
        return $this->usesDatabaseRuntime() ? $this->routingRuleRepository->findOneTypeApplicationByDomain($domain) : $this->runtimeConfig->applicationRuleForDomain($domain);
    }

    public function getApplicationRuleForProject(Project $project): ?RoutingRule
    {
        return $this->usesDatabaseRuntime() ? $this->routingRuleRepository->findOneTypeApplicationByProject($project) : $this->runtimeConfig->applicationRuleForProject($project);
    }

    public function getApplicationRuleForDomainAndProject(Domain $domain, Project $project): ?RoutingRule
    {
        return $this->usesDatabaseRuntime() ? $this->routingRuleRepository->findOneTypeApplicationByDomainAndProject($domain, $project) : $this->runtimeConfig->applicationRuleForDomainAndProject($domain, $project);
    }

    public function getApplicationRuleForApplication(Application|string $application): ?RoutingRule
    {
        $applicationId = $application instanceof Application ? (string) $application->getId() : $application;

        return $this->usesDatabaseRuntime() ? $this->routingRuleRepository->findOneTypeApplicationByApplicationId($applicationId) : $this->runtimeConfig->applicationRuleByApplicationId($applicationId);
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

    private function usesDatabaseRuntime(): bool
    {
        return ($_SERVER['APP_ENV'] ?? null) === 'test';
    }
}
