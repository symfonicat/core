<?php

namespace Symfonicat\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\ApplicationEnv;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\DomainEnv;
use Symfonicat\Entity\Electron;
use Symfonicat\Entity\ElectronEnv;
use Symfonicat\Entity\Env;
use Symfonicat\Entity\EnvParent;
use Symfonicat\Entity\Module;
use Symfonicat\Entity\Project;
use Symfonicat\Entity\ProjectEnv;
use Symfonicat\Entity\RoutingRule;

final class RuntimeConfig
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $catalog = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function domainByHost(string $host): ?Domain
    {
        return $this->catalog()['domains'][trim($host)] ?? null;
    }

    /**
     * @return list<Domain>
     */
    public function domains(): array
    {
        return array_values($this->catalog()['domains']);
    }

    /**
     * @return list<Project>
     */
    public function projects(): array
    {
        return array_values($this->catalog()['projects']);
    }

    /**
     * @return list<Application>
     */
    public function applications(): array
    {
        return array_values($this->catalog()['applications']);
    }

    /**
     * @return list<Module>
     */
    public function modules(): array
    {
        return array_values($this->catalog()['modules']);
    }

    public function projectByIdForDomain(string $id, Domain $domain): ?Project
    {
        $project = $this->projectByFullOrCleanId($id);

        return $project instanceof Project && $project->hasDomain($domain) ? $project : null;
    }

    public function projectByFullOrCleanId(string $id): ?Project
    {
        $project = $this->singleByFullOrCleanId($this->catalog()['projects'], $id, 'Project');

        return $project instanceof Project ? $project : null;
    }

    public function applicationByFullOrCleanId(string $id): ?Application
    {
        $application = $this->singleByFullOrCleanId($this->catalog()['applications'], $id, 'Application');

        return $application instanceof Application ? $application : null;
    }

    public function moduleByFullOrCleanId(string $id): ?Module
    {
        $module = $this->singleByFullOrCleanId($this->catalog()['modules'], $id, 'Module');

        return $module instanceof Module ? $module : null;
    }

    /**
     * @return list<RoutingRule>
     */
    public function domainRules(Domain $domain): array
    {
        return array_values(array_filter(
            $this->catalog()['rules'],
            static fn (RoutingRule $rule): bool => $rule->isDomainType() && $rule->getDomain()?->getId() === $domain->getId(),
        ));
    }

    /**
     * @return list<RoutingRule>
     */
    public function projectRules(Project $project): array
    {
        return array_values(array_filter(
            $this->catalog()['rules'],
            static fn (RoutingRule $rule): bool => $rule->isProjectType() && $rule->getProject()?->getId() === $project->getId(),
        ));
    }

    public function redirectRuleForDomain(Domain $domain): ?RoutingRule
    {
        return $this->firstRule(static fn (RoutingRule $rule): bool => $rule->isRedirectRule()
            && $rule->isDomainRedirectType()
            && $rule->getDomain()?->getId() === $domain->getId());
    }

    public function redirectRuleForProject(Project $project): ?RoutingRule
    {
        return $this->firstRule(static fn (RoutingRule $rule): bool => $rule->isRedirectRule()
            && $rule->isProjectRedirectType()
            && $rule->getProject()?->getId() === $project->getId());
    }

    public function routeRuleForDomain(Domain $domain): ?RoutingRule
    {
        return $this->firstRule(static fn (RoutingRule $rule): bool => $rule->isRouteRule()
            && $rule->isDomainRouteType()
            && $rule->getDomain()?->getId() === $domain->getId());
    }

    public function routeRuleForProject(Project $project): ?RoutingRule
    {
        return $this->firstRule(static fn (RoutingRule $rule): bool => $rule->isRouteRule()
            && $rule->isProjectRouteType()
            && $rule->getProject()?->getId() === $project->getId());
    }

    public function applicationRuleByRoute(string $route): ?RoutingRule
    {
        $route = trim($route);

        return $this->firstRule(static fn (RoutingRule $rule): bool => $rule->isApplicationType()
            && $rule->isApplicationRouteType()
            && $rule->getRoute() === $route);
    }

    public function applicationRuleByApplicationId(string $applicationId, ?string $applicationType = null): ?RoutingRule
    {
        $application = $this->applicationByFullOrCleanId($applicationId);
        if (!$application instanceof Application) {
            return null;
        }

        return $this->firstRule(static fn (RoutingRule $rule): bool => $rule->isApplicationType()
            && ($applicationType === null || $rule->getApplicationType() === $applicationType)
            && $rule->getApplication()?->getId() === $application->getId());
    }

    public function applicationRuleForDomain(Domain $domain): ?RoutingRule
    {
        return $this->firstRule(static fn (RoutingRule $rule): bool => $rule->isApplicationType()
            && $rule->isApplicationDomainType()
            && $rule->getApplication() instanceof Application
            && $rule->getDomain()?->getId() === $domain->getId());
    }

    public function applicationRuleForProject(Project $project): ?RoutingRule
    {
        return $this->firstRule(static fn (RoutingRule $rule): bool => $rule->isApplicationType()
            && $rule->isApplicationProjectType()
            && $rule->getApplication() instanceof Application
            && $rule->getProject()?->getId() === $project->getId());
    }

    public function applicationRuleForDomainAndProject(Domain $domain, Project $project): ?RoutingRule
    {
        return $this->firstRule(static fn (RoutingRule $rule): bool => $rule->isApplicationType()
            && $rule->isApplicationDomainProjectType()
            && $rule->getApplication() instanceof Application
            && $rule->getDomain()?->getId() === $domain->getId()
            && $rule->getProject()?->getId() === $project->getId());
    }

    /**
     * @return list<RoutingRule>
     */
    public function applicationArgumentRules(): array
    {
        return array_values(array_filter(
            $this->catalog()['rules'],
            static fn (RoutingRule $rule): bool => $rule->isApplicationType()
                && $rule->isApplicationArgumentsType()
                && $rule->getApplication() instanceof Application,
        ));
    }

    public function electronById(string $id): ?Electron
    {
        return $this->catalog()['electrons'][trim($id)] ?? null;
    }

    /**
     * @return list<Electron>
     */
    public function electrons(): array
    {
        return array_values($this->catalog()['electrons']);
    }

    public function electronForDomain(Domain $domain): ?Electron
    {
        return $this->firstElectron(static fn (Electron $electron): bool => $electron->isDomainType()
            && $electron->getDomain()?->getId() === $domain->getId());
    }

    public function electronForProject(Project $project, ?Domain $domain = null): ?Electron
    {
        return $this->firstElectron(static fn (Electron $electron): bool => $electron->isProjectType()
            && $electron->getProject()?->getId() === $project->getId()
            && (!$domain instanceof Domain || $electron->getDomain()?->getId() === $domain->getId()));
    }

    public function electronForApplication(Application $application): ?Electron
    {
        return $this->firstElectron(static fn (Electron $electron): bool => $electron->isApplicationType()
            && $electron->getApplication()?->getId() === $application->getId());
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        $rows = $this->readAdminRows();

        $domains = [];
        foreach ($this->rows($rows, 'symfonicat_domain') as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id !== '') {
                $domains[$id] = (new Domain())->setId($id);
            }
        }

        $projects = [];
        foreach ($this->rows($rows, 'symfonicat_project') as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id !== '') {
                $projects[$id] = (new Project())->setId($id);
            }
        }

        $applications = [];
        foreach ($this->rows($rows, 'symfonicat_application') as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id !== '') {
                $applications[$id] = (new Application())->setId($id);
            }
        }

        $modules = [];
        foreach ($this->rows($rows, 'symfonicat_module') as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id !== '') {
                $modules[$id] = (new Module())
                    ->setId($id)
                    ->setPackage(isset($row['package']) ? (string) $row['package'] : null);
            }
        }

        foreach ($this->rows($rows, 'symfonicat_domain_project') as $row) {
            $domain = $domains[(string) ($row['domain_id'] ?? '')] ?? null;
            $project = $projects[(string) ($row['project_id'] ?? '')] ?? null;
            if ($domain instanceof Domain && $project instanceof Project) {
                $domain->addProject($project);
            }
        }

        foreach ($this->rows($rows, 'symfonicat_module_domain') as $row) {
            $module = $modules[(string) ($row['module_id'] ?? '')] ?? null;
            $domain = $domains[(string) ($row['domain_id'] ?? '')] ?? null;
            if ($module instanceof Module && $domain instanceof Domain) {
                $module->addDomain($domain);
            }
        }

        foreach ($this->rows($rows, 'symfonicat_module_project') as $row) {
            $module = $modules[(string) ($row['module_id'] ?? '')] ?? null;
            $project = $projects[(string) ($row['project_id'] ?? '')] ?? null;
            if ($module instanceof Module && $project instanceof Project) {
                $module->addProject($project);
            }
        }

        foreach ($this->rows($rows, 'symfonicat_module_application') as $row) {
            $module = $modules[(string) ($row['module_id'] ?? '')] ?? null;
            $application = $applications[(string) ($row['application_id'] ?? '')] ?? null;
            if ($module instanceof Module && $application instanceof Application) {
                $module->addApplication($application);
            }
        }

        $envParents = [];
        foreach ($this->rows($rows, 'symfonicat_env_parent') as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id !== '') {
                $envParents[$id] = (new EnvParent())->setId($id);
            }
        }

        $envs = [];
        foreach ($this->rows($rows, 'symfonicat_env') as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            $parent = $envParents[(string) ($row['env_parent_id'] ?? '')] ?? null;
            if ($id !== '' && $parent instanceof EnvParent) {
                $envs[$id] = (new Env())->setId($id)->setEnvParent($parent);
            }
        }

        foreach ($this->rows($rows, 'symfonicat_domain_env') as $row) {
            $domain = $domains[(string) ($row['domain_id'] ?? '')] ?? null;
            $env = $envs[(string) ($row['env_id'] ?? '')] ?? null;
            if ($domain instanceof Domain && $env instanceof Env) {
                $domain->addEnv((new DomainEnv())->setEnv($env)->setValue((string) ($row['value'] ?? '')));
            }
        }

        foreach ($this->rows($rows, 'symfonicat_project_env') as $row) {
            $project = $projects[(string) ($row['project_id'] ?? '')] ?? null;
            $env = $envs[(string) ($row['env_id'] ?? '')] ?? null;
            if ($project instanceof Project && $env instanceof Env) {
                $project->addEnv((new ProjectEnv())->setEnv($env)->setValue((string) ($row['value'] ?? '')));
            }
        }

        foreach ($this->rows($rows, 'symfonicat_application_env') as $row) {
            $application = $applications[(string) ($row['application_id'] ?? '')] ?? null;
            $env = $envs[(string) ($row['env_id'] ?? '')] ?? null;
            if ($application instanceof Application && $env instanceof Env) {
                $application->addEnv((new ApplicationEnv())->setEnv($env)->setValue((string) ($row['value'] ?? '')));
            }
        }

        $electrons = [];
        foreach ($this->rows($rows, 'symfonicat_electron') as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $electron = (new Electron())
                ->setId($id)
                ->setName((string) ($row['name'] ?? $id))
                ->setType((string) ($row['type'] ?? Electron::TYPE_DOMAIN))
                ->setDomain($domains[(string) ($row['domain_id'] ?? '')] ?? null)
                ->setProject($projects[(string) ($row['project_id'] ?? '')] ?? null)
                ->setApplication($applications[(string) ($row['application_id'] ?? '')] ?? null);

            $electrons[$id] = $electron;
        }

        foreach ($this->rows($rows, 'symfonicat_electron_env') as $row) {
            $electron = $electrons[(string) ($row['electron_id'] ?? '')] ?? null;
            $env = $envs[(string) ($row['env_id'] ?? '')] ?? null;
            if ($electron instanceof Electron && $env instanceof Env) {
                $electron->addEnv((new ElectronEnv())->setEnv($env)->setValue((string) ($row['value'] ?? '')));
            }
        }

        $rules = [];
        foreach ($this->rows($rows, 'symfonicat_routing_rule') as $row) {
            $rule = (new RoutingRule())
                ->setType((string) ($row['type'] ?? RoutingRule::TYPE_DOMAIN))
                ->setArguments(is_array($row['arguments'] ?? null) ? $row['arguments'] : [])
                ->setRedirectType(isset($row['redirect_type']) ? (string) $row['redirect_type'] : null)
                ->setRedirectTarget(isset($row['redirect_target']) ? (string) $row['redirect_target'] : null)
                ->setRouteType(isset($row['route_type']) ? (string) $row['route_type'] : null)
                ->setApplicationType(isset($row['application_type']) ? (string) $row['application_type'] : null)
                ->setRoute(isset($row['route']) ? (string) $row['route'] : null)
                ->setDomain($domains[(string) ($row['domain_id'] ?? '')] ?? null)
                ->setProject($projects[(string) ($row['project_id'] ?? '')] ?? null)
                ->setApplication($applications[(string) ($row['application_id'] ?? '')] ?? null)
                ->setRedirectDomain($domains[(string) ($row['redirect_domain_id'] ?? '')] ?? null)
                ->setRedirectProject($projects[(string) ($row['redirect_project_id'] ?? '')] ?? null);

            $rules[] = $rule;
        }

        return $this->catalog = [
            'domains' => $domains,
            'projects' => $projects,
            'applications' => $applications,
            'modules' => $modules,
            'electrons' => $electrons,
            'rules' => $rules,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readAdminRows(): array
    {
        $path = rtrim($this->projectDir, '/').'/config/packages/symfonicat.yaml';
        if (!is_file($path)) {
            return [];
        }

        $config = Yaml::parseFile($path);
        if (!is_array($config)) {
            return [];
        }

        $rows = $config['symfonicat']['admin'] ?? [];

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function rows(array $rows, string $table): array
    {
        $tableRows = $rows[$table] ?? [];
        if (!is_array($tableRows)) {
            return [];
        }

        return array_values(array_filter($tableRows, 'is_array'));
    }

    /**
     * @param array<string, object> $items
     */
    private function singleByFullOrCleanId(array $items, string $id, string $label): ?object
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $matches = [];
        foreach ($items as $itemId => $item) {
            $cleanId = str_contains($itemId, '/') ? substr($itemId, strrpos($itemId, '/') + 1) : $itemId;
            if ($itemId === $id || $cleanId === $id) {
                $matches[$itemId] = $item;
            }
        }

        if ($matches === []) {
            return null;
        }

        if (count($matches) > 1 && !str_contains($id, '/')) {
            throw new \RuntimeException(sprintf('%s id "%s" is ambiguous. Matching ids: %s', $label, $id, implode(', ', array_keys($matches))));
        }

        return reset($matches) ?: null;
    }

    private function firstRule(callable $predicate): ?RoutingRule
    {
        foreach ($this->catalog()['rules'] as $rule) {
            if ($predicate($rule)) {
                return $rule;
            }
        }

        return null;
    }

    private function firstElectron(callable $predicate): ?Electron
    {
        foreach ($this->catalog()['electrons'] as $electron) {
            if ($predicate($electron)) {
                return $electron;
            }
        }

        return null;
    }
}
