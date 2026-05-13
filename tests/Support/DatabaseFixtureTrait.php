<?php

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Application;
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
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shared fixture helpers for Symfonicat's kernel- and web-based tests.
 *
 * Keeps every test's setUp cheap (delete-based truncation; no schema
 * recreation between tests) and exposes small builder methods so tests
 * describe *what* scenario they want rather than *how* to persist it.
 */
trait DatabaseFixtureTrait
{
    abstract protected static function getTestContainer(): ContainerInterface;

    protected function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = self::getTestContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }

    /**
     * Wipe every Symfonicat table without dropping the schema. Faster than
     * SchemaTool::dropDatabase + createSchema, safe on SQLite because we
     * disable foreign key enforcement for the duration of the truncate.
     */
    protected function truncateSymfonicatTables(): void
    {
        $connection = $this->entityManager()->getConnection();
        $platform = $connection->getDatabasePlatform();
        $platformName = (new \ReflectionClass($platform))->getShortName();
        $isSqlite = str_contains(strtolower($platformName), 'sqlite');

        $tables = [
            // Children first to keep the intent readable even though FK checks are off.
            'symfonicat_routing_rule',
            'symfonicat_electron_env',
            'symfonicat_application_env',
            'symfonicat_project_env',
            'symfonicat_domain_env',
            'symfonicat_module_application',
            'symfonicat_module_project',
            'symfonicat_module_domain',
            'symfonicat_domain_project',
            'symfonicat_electron',
            'symfonicat_application',
            'symfonicat_module',
            'symfonicat_project',
            'symfonicat_domain',
            'symfonicat_env',
            'symfonicat_env_parent',
            'symfonicat_admin',
        ];

        if ($isSqlite) {
            $connection->executeStatement('PRAGMA foreign_keys = OFF');
        }

        try {
            foreach ($tables as $table) {
                $schema = $connection->createSchemaManager();
                if (!$schema->tablesExist([$table])) {
                    continue;
                }

                $connection->executeStatement(sprintf('DELETE FROM %s', $platform->quoteIdentifier($table)));
            }
        } finally {
            if ($isSqlite) {
                $connection->executeStatement('PRAGMA foreign_keys = ON');
            }
        }

        $this->entityManager()->clear();
    }

    protected function createDomain(string $id): Domain
    {
        $domain = (new Domain())
            ->setId($this->vendorScopedId($id));

        $this->entityManager()->persist($domain);
        $this->entityManager()->flush();

        return $domain;
    }

    protected function createProject(string $id, ?Domain $domain = null): Project
    {
        $project = (new Project())
            ->setId($this->vendorScopedId($id));

        if ($domain instanceof Domain) {
            $domain->addProject($project);
        }

        $this->entityManager()->persist($project);
        $this->entityManager()->flush();

        return $project;
    }

    protected function createApplication(string $id): Application
    {
        $application = (new Application())
            ->setId($this->vendorScopedId($id));

        $this->entityManager()->persist($application);
        $this->entityManager()->flush();

        return $application;
    }

    protected function createModule(string $id, ?string $package = null): Module
    {
        $module = (new Module())
            ->setId($this->vendorScopedId($id))
            ->setPackage($package ?? $id);

        $this->entityManager()->persist($module);
        $this->entityManager()->flush();

        return $module;
    }

    protected function createElectron(string $name, string $type, ?Domain $domain = null, ?Project $project = null): Electron
    {
        $electron = (new Electron())
            ->setId('core/'.strtolower(str_replace(' ', '-', $name)))
            ->setName($name)
            ->setType($type)
            ->setDomain($domain)
            ->setProject($project);

        $this->entityManager()->persist($electron);
        $this->entityManager()->flush();

        return $electron;
    }

    private function vendorScopedId(string $id): string
    {
        return str_contains($id, '/') ? $id : 'core/'.$id;
    }

    protected function createEnv(string $id, string $envParentId = 'colors'): Env
    {
        $envParent = $this->entityManager()->find(EnvParent::class, $envParentId);

        if (!$envParent instanceof EnvParent) {
            $envParent = (new EnvParent())->setId($envParentId);
            $this->entityManager()->persist($envParent);
        }

        $env = (new Env())
            ->setId($id)
            ->setEnvParent($envParent);

        $this->entityManager()->persist($env);
        $this->entityManager()->flush();

        return $env;
    }

    protected function setDomainEnv(Domain $domain, Env $env, string $value): DomainEnv
    {
        $domainEnv = (new DomainEnv())
            ->setEnv($env)
            ->setValue($value);

        $domain->addEnv($domainEnv);
        $this->entityManager()->persist($domainEnv);
        $this->entityManager()->flush();

        return $domainEnv;
    }

    protected function setProjectEnv(Project $project, Env $env, string $value): ProjectEnv
    {
        $projectEnv = (new ProjectEnv())
            ->setEnv($env)
            ->setValue($value);

        $project->addEnv($projectEnv);
        $this->entityManager()->persist($projectEnv);
        $this->entityManager()->flush();

        return $projectEnv;
    }

    protected function setElectronEnv(Electron $electron, Env $env, string $value): ElectronEnv
    {
        $electronEnv = (new ElectronEnv())
            ->setEnv($env)
            ->setValue($value);

        $electron->addEnv($electronEnv);
        $this->entityManager()->persist($electronEnv);
        $this->entityManager()->flush();

        return $electronEnv;
    }

    protected function createDomainRoutingRule(Domain $domain, string $argument): RoutingRule
    {
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_DOMAIN)
            ->setDomain($domain)
            ->setArguments([$argument]);

        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        return $rule;
    }

    protected function createProjectRoutingRule(Project $project, string $argument): RoutingRule
    {
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_PROJECT)
            ->setProject($project)
            ->setArguments([$argument]);

        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        return $rule;
    }
}
