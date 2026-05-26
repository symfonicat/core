<?php

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Parcel;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\ApplicationEnv;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\DomainEnv;
use Symfonicat\Entity\Endpoint;
use Symfonicat\Entity\EndpointEnv;
use Symfonicat\Entity\Env;
use Symfonicat\Entity\EnvParent;
use Symfonicat\Entity\Module;
use Symfonicat\Entity\Subdomain;
use Symfonicat\Entity\SubdomainEnv;
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
            'symfonicat_application_env',
            'symfonicat_parcel_env',
            'symfonicat_subdomain_env',
            'symfonicat_domain_env',
            'symfonicat_endpoint_env',
            'symfonicat_endpoint_module',
            'symfonicat_endpoint_middleware',
            'symfonicat_module_endpoint',
            'symfonicat_module_application',
            'symfonicat_module_subdomain',
            'symfonicat_module_domain',
            'symfonicat_subdomain_middleware',
            'symfonicat_domain_middleware',
            'symfonicat_domain_subdomain',
            'symfonicat_endpoint',
            'symfonicat_middleware',
            'symfonicat_application',
            'symfonicat_application',
            'symfonicat_module',
            'symfonicat_subdomain',
            'symfonicat_domain',
            'symfonicat_parcel',
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
            ->setId($id);

        $this->entityManager()->persist($domain);
        $this->entityManager()->flush();

        return $domain;
    }

    protected function createSubdomain(string $id, ?Domain $domain = null): Subdomain
    {
        $subdomain = (new Subdomain())
            ->setId($this->vendorScopedId($id));

        if ($domain instanceof Domain) {
            $domain->addSubdomain($subdomain);
        }

        $this->entityManager()->persist($subdomain);
        $this->entityManager()->flush();

        return $subdomain;
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

    protected function createApplication(string $idOrName, ?string $type = null, ?Domain $domain = null, ?Subdomain $subdomain = null, ?Endpoint $endpoint = null): Application
    {
        $application = (new Application())
            ->setId(
                $type === null
                    ? $this->vendorScopedId($idOrName)
                    : strtolower(str_replace(' ', '-', $idOrName)),
            )
            ->setName($type === null ? basename(str_replace('\\', '/', $idOrName)) ?: $idOrName : $idOrName);

        if ($type !== null) {
            $application->setDomain($domain);

            if ($type === Application::TYPE_SUBDOMAIN) {
                $application
                    ->setSubdomain($subdomain)
                    ->setEndpoint(null);
            } elseif ($type === Application::TYPE_ENDPOINT) {
                $application
                    ->setSubdomain(null)
                    ->setEndpoint($endpoint);
            } else {
                $application
                    ->setSubdomain(null)
                    ->setEndpoint(null);
            }
        } else {
            $application
                ->setDomain($domain)
                ->setSubdomain($subdomain)
                ->setEndpoint($endpoint);
        }

        $this->entityManager()->persist($application);
        $this->entityManager()->flush();

        return $application;
    }

    protected function createEndpoint(string $id, ?Parcel $parcel = null): Endpoint
    {
        $endpoint = (new Endpoint())
            ->setId($id)
            ->setParcel($parcel);

        $this->entityManager()->persist($endpoint);
        $this->entityManager()->flush();

        return $endpoint;
    }

    protected function setEndpointEnv(Endpoint $endpoint, Env $env, string $value): EndpointEnv
    {
        $endpointEnv = (new EndpointEnv())
            ->setEnv($env)
            ->setValue($value);

        $endpoint->addEnv($endpointEnv);
        $this->entityManager()->persist($endpointEnv);
        $this->entityManager()->flush();

        return $endpointEnv;
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

    protected function setSubdomainEnv(Subdomain $subdomain, Env $env, string $value): SubdomainEnv
    {
        $subdomainEnv = (new SubdomainEnv())
            ->setEnv($env)
            ->setValue($value);

        $subdomain->addEnv($subdomainEnv);
        $this->entityManager()->persist($subdomainEnv);
        $this->entityManager()->flush();

        return $subdomainEnv;
    }

    protected function setApplicationEnv(Application $application, Env $env, string $value): ApplicationEnv
    {
        $applicationEnv = (new ApplicationEnv())
            ->setEnv($env)
            ->setValue($value);

        $application->addEnv($applicationEnv);
        $this->entityManager()->persist($applicationEnv);
        $this->entityManager()->flush();

        return $applicationEnv;
    }
}
