<?php

namespace Symfonicat\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;
use Symfonicat\Entity\Bundle;
use Symfonicat\Entity\BundleEnv;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\DomainEnv;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\ApplicationEnv;
use Symfonicat\Entity\Endpoint;
use Symfonicat\Entity\EndpointEnv;
use Symfonicat\Entity\Middleware;
use Symfonicat\Entity\Env;
use Symfonicat\Entity\EnvParent;
use Symfonicat\Entity\Module;
use Symfonicat\Entity\Subdomain;
use Symfonicat\Entity\SubdomainEnv;
use Symfonicat\Entity\RoutingRule;

final class RuntimeConfig
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $catalog = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $subdomainDir,
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
     * @return list<Subdomain>
     */
    public function subdomains(): array
    {
        return array_values($this->catalog()['subdomains']);
    }

    /**
     * @return list<Module>
     */
    public function modules(): array
    {
        return array_values($this->catalog()['modules']);
    }

    /**
     * @return list<Bundle>
     */
    public function bundles(): array
    {
        return array_values($this->catalog()['bundles']);
    }

    public function subdomainByIdForDomain(string $id, Domain $domain): ?Subdomain
    {
        $subdomain = $this->subdomainByFullOrCleanId($id);

        return $subdomain instanceof Subdomain && $subdomain->hasDomain($domain) ? $subdomain : null;
    }

    public function subdomainByFullOrCleanId(string $id): ?Subdomain
    {
        $subdomain = $this->singleByFullOrCleanId($this->catalog()['subdomains'], $id, 'Subdomain');

        return $subdomain instanceof Subdomain ? $subdomain : null;
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
    public function subdomainRules(Subdomain $subdomain): array
    {
        return array_values(array_filter(
            $this->catalog()['rules'],
            static fn (RoutingRule $rule): bool => $rule->isSubdomainType() && $rule->getSubdomain()?->getId() === $subdomain->getId(),
        ));
    }

    public function redirectRuleForDomain(Domain $domain): ?RoutingRule
    {
        return $this->firstRule(static fn (RoutingRule $rule): bool => $rule->isRedirectRule()
            && $rule->isDomainRedirectType()
            && $rule->getDomain()?->getId() === $domain->getId());
    }

    public function redirectRuleForSubdomain(Subdomain $subdomain): ?RoutingRule
    {
        return $this->firstRule(static fn (RoutingRule $rule): bool => $rule->isRedirectRule()
            && $rule->isSubdomainRedirectType()
            && $rule->getSubdomain()?->getId() === $subdomain->getId());
    }

    public function routeRuleForDomain(Domain $domain): ?RoutingRule
    {
        return $this->firstRule(static fn (RoutingRule $rule): bool => $rule->isRouteRule()
            && $rule->isDomainRouteType()
            && $rule->getDomain()?->getId() === $domain->getId());
    }

    public function routeRuleForSubdomain(Subdomain $subdomain): ?RoutingRule
    {
        return $this->firstRule(static fn (RoutingRule $rule): bool => $rule->isRouteRule()
            && $rule->isSubdomainRouteType()
            && $rule->getSubdomain()?->getId() === $subdomain->getId());
    }

    public function applicationById(string $id): ?Application
    {
        return $this->catalog()['applications'][trim($id)] ?? null;
    }

    /**
     * @return list<Application>
     */
    public function applications(): array
    {
        return array_values($this->catalog()['applications']);
    }

    /**
     * @return list<Middleware>
     */
    public function middlewares(): array
    {
        return array_values($this->catalog()['middlewares']);
    }

    public function applicationForDomain(Domain $domain): ?Application
    {
        return $this->firstApplication(static fn (Application $application): bool => $application->isDomainType()
            && $application->getDomain()?->getId() === $domain->getId());
    }

    public function applicationForSubdomain(Subdomain $subdomain, ?Domain $domain = null): ?Application
    {
        return $this->firstApplication(static fn (Application $application): bool => $application->isSubdomainType()
            && $application->getSubdomain()?->getId() === $subdomain->getId()
            && (!$domain instanceof Domain || $application->getDomain()?->getId() === $domain->getId()));
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

        $bundles = [];
        foreach ($this->rows($rows, 'symfonicat_bundle') as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id !== '') {
                $bundles[$id] = (new Bundle())
                    ->setId($id)
                    ->setPath((string) ($row['path'] ?? ''));
            }
        }

        $domains = [];
        foreach ($this->rows($rows, 'symfonicat_domain') as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id !== '') {
                $domains[$id] = (new Domain())
                    ->setId($id)
                    ->setBundle($bundles[(string) ($row['bundle_id'] ?? '')] ?? null);
            }
        }

        $subdomains = [];
        foreach ($this->rows($rows, 'symfonicat_subdomain') as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id !== '') {
                $subdomains[$id] = (new Subdomain())
                    ->setId($id)
                    ->setBundle($bundles[(string) ($row['bundle_id'] ?? '')] ?? null);
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

        foreach ($this->rows($rows, 'symfonicat_domain_subdomain') as $row) {
            $domain = $domains[(string) ($row['domain_id'] ?? '')] ?? null;
            $subdomain = $subdomains[(string) ($row['subdomain_id'] ?? '')] ?? null;
            if ($domain instanceof Domain && $subdomain instanceof Subdomain) {
                $domain->addSubdomain($subdomain);
            }
        }

        foreach ($this->rows($rows, 'symfonicat_module_domain') as $row) {
            $module = $modules[(string) ($row['module_id'] ?? '')] ?? null;
            $domain = $domains[(string) ($row['domain_id'] ?? '')] ?? null;
            if ($module instanceof Module && $domain instanceof Domain) {
                $module->addDomain($domain);
            }
        }

        foreach ($this->rows($rows, 'symfonicat_module_subdomain') as $row) {
            $module = $modules[(string) ($row['module_id'] ?? '')] ?? null;
            $subdomain = $subdomains[(string) ($row['subdomain_id'] ?? '')] ?? null;
            if ($module instanceof Module && $subdomain instanceof Subdomain) {
                $module->addSubdomain($subdomain);
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

        foreach ($this->rows($rows, 'symfonicat_subdomain_env') as $row) {
            $subdomain = $subdomains[(string) ($row['subdomain_id'] ?? '')] ?? null;
            $env = $envs[(string) ($row['env_id'] ?? '')] ?? null;
            if ($subdomain instanceof Subdomain && $env instanceof Env) {
                $subdomain->addEnv((new SubdomainEnv())->setEnv($env)->setValue((string) ($row['value'] ?? '')));
            }
        }

        foreach ($this->rows($rows, 'symfonicat_bundle_env') as $row) {
            $bundle = $bundles[(string) ($row['bundle_id'] ?? '')] ?? null;
            $env = $envs[(string) ($row['env_id'] ?? '')] ?? null;
            if ($bundle instanceof Bundle && $env instanceof Env) {
                $bundle->addEnv((new BundleEnv())->setEnv($env)->setValue((string) ($row['value'] ?? '')));
            }
        }

        $endpoints = [];
        foreach ($this->rows($rows, 'symfonicat_endpoint') as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $endpoint = (new Endpoint())
                ->setId($id)
                ->setBundle($bundles[(string) ($row['bundle_id'] ?? '')] ?? null)
                ->setCatch((bool) ($row['catch'] ?? false))
                ->setArguments(isset($row['arguments']) && is_array($row['arguments']) ? $row['arguments'] : []);

            $endpoints[$id] = $endpoint;
        }

        foreach ($this->rows($rows, 'symfonicat_endpoint_env') as $row) {
            $endpoint = $endpoints[(string) ($row['endpoint_id'] ?? '')] ?? null;
            $env = $envs[(string) ($row['env_id'] ?? '')] ?? null;
            if ($endpoint instanceof Endpoint && $env instanceof Env) {
                $endpoint->addEnv((new EndpointEnv())->setEnv($env)->setValue((string) ($row['value'] ?? '')));
            }
        }

        foreach ($this->rows($rows, 'symfonicat_endpoint_module') as $row) {
            $endpoint = $endpoints[(string) ($row['endpoint_id'] ?? '')] ?? null;
            $module = $modules[(string) ($row['module_id'] ?? '')] ?? null;
            if ($endpoint instanceof Endpoint && $module instanceof Module) {
                $endpoint->addModule($module);
            }
        }

        foreach ($this->rows($rows, 'symfonicat_endpoint_middleware') as $row) {
            $endpoint = $endpoints[(string) ($row['endpoint_id'] ?? '')] ?? null;
            $middleware = $middlewares[(int) ($row['middleware_id'] ?? 0)] ?? null;
            if ($endpoint instanceof Endpoint && $middleware instanceof Middleware) {
                $endpoint->addMiddleware($middleware);
            }
        }

        $applications = [];
        foreach ($this->rows($rows, 'symfonicat_application') as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $application = (new Application())
                ->setId($id)
                ->setName((string) ($row['name'] ?? $id))
                ->setType((string) ($row['type'] ?? Application::TYPE_DOMAIN))
                ->setDomain($domains[(string) ($row['domain_id'] ?? '')] ?? null)
                ->setSubdomain($subdomains[(string) ($row['subdomain_id'] ?? '')] ?? null)
                ->setEndpoint($endpoints[(string) ($row['endpoint_id'] ?? '')] ?? null);

            $applications[$id] = $application;
        }

        $middlewares = [];
        foreach ($this->rows($rows, 'symfonicat_middleware') as $row) {
            $id = (int) ($row['id'] ?? 0);
            $class = trim((string) ($row['class'] ?? ''));
            if ($id > 0 && $class !== '') {
                $middlewares[$id] = (new Middleware())
                    ->setClass($class);
            }
        }

        foreach ($this->rows($rows, 'symfonicat_domain_middleware') as $row) {
            $domain = $domains[(string) ($row['domain_id'] ?? '')] ?? null;
            $middleware = $middlewares[(int) ($row['middleware_id'] ?? 0)] ?? null;
            if ($domain instanceof Domain && $middleware instanceof Middleware) {
                $domain->addMiddleware($middleware);
            }
        }

        foreach ($this->rows($rows, 'symfonicat_subdomain_middleware') as $row) {
            $subdomain = $subdomains[(string) ($row['subdomain_id'] ?? '')] ?? null;
            $middleware = $middlewares[(int) ($row['middleware_id'] ?? 0)] ?? null;
            if ($subdomain instanceof Subdomain && $middleware instanceof Middleware) {
                $subdomain->addMiddleware($middleware);
            }
        }

        foreach ($this->rows($rows, 'symfonicat_application_env') as $row) {
            $application = $applications[(string) ($row['application_id'] ?? '')] ?? null;
            $env = $envs[(string) ($row['env_id'] ?? '')] ?? null;
            if ($application instanceof Application && $env instanceof Env) {
                $application->addEnv((new ApplicationEnv())->setEnv($env)->setValue((string) ($row['value'] ?? '')));
            }
        }

        return $this->catalog = [
            'bundles' => $bundles,
            'domains' => $domains,
            'subdomains' => $subdomains,
            'modules' => $modules,
            'applications' => $applications,
            'endpoints' => $endpoints,
            'middlewares' => $middlewares,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readAdminRows(): array
    {
        $path = rtrim($this->subdomainDir, '/').'/config/packages/symfonicat.yaml';
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

    private function firstApplication(callable $predicate): ?Application
    {
        foreach ($this->catalog()['applications'] as $application) {
            if ($predicate($application)) {
                return $application;
            }
        }

        return null;
    }
}
