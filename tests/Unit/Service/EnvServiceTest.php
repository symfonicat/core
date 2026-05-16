<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\ApplicationEnv;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\DomainEnv;
use Symfonicat\Entity\Electron;
use Symfonicat\Entity\ElectronEnv;
use Symfonicat\Entity\Env;
use Symfonicat\Entity\EnvParent;
use Symfonicat\Entity\Project;
use Symfonicat\Entity\ProjectEnv;
use Symfonicat\Service\ApplicationService;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\ElectronService;
use Symfonicat\Service\EnvService;
use Symfonicat\Service\ProjectService;

/**
 * Pure unit coverage of EnvService — the "env overlay" abstraction the rest of
 * the framework depends on. No container, no database: the service
 * collaborates with three DI-injected services that we stub directly.
 */
final class EnvServiceTest extends TestCase
{
    public function testReturnsEmptyArrayWhenNothingIsLoaded(): void
    {
        $service = $this->makeService(null, null);

        self::assertSame([], $service->all());
        self::assertNull($service->get('colors.primary'));
    }

    public function testReturnsDomainValuesWhenOnlyDomainIsLoaded(): void
    {
        $env = $this->makeEnv('primary');
        $domain = $this->makeDomain('example.com', [$env->getId() => 'blue']);

        $service = $this->makeService($domain, null);

        self::assertSame(['colors' => ['primary' => 'blue']], $service->all());
        self::assertSame('blue', $service->get('colors.primary'));
        self::assertNull($service->get('colors.missing'));
    }

    public function testProjectValuesOverlayDomainValuesWhenProjectBelongsToDomain(): void
    {
        $color = $this->makeEnv('primary');
        $theme = $this->makeEnv('theme');

        $domain = $this->makeDomain('example.com', [
            $color->getId() => 'blue',
            $theme->getId() => 'default',
        ]);

        $subdomain = $this->makeProject('subdomain1', [
            $color->getId() => 'green',
        ]);

        $domain->addProject($subdomain);

        $service = $this->makeService($domain, $subdomain);

        self::assertSame(
            ['colors' => ['primary' => 'green', 'theme' => 'default']],
            $service->all(),
            'subdomain values should overlay matching domain keys; non-overlapping domain keys remain',
        );
        self::assertSame('green', $service->get('colors.primary'));
        self::assertSame('default', $service->get('colors.theme'));
    }

    public function testProjectValuesOverlayDomainAndApplicationValues(): void
    {
        $color = $this->makeEnv('primary');
        $theme = $this->makeEnv('theme');
        $mode = $this->makeEnv('mode');

        $application = $this->makeApplication('test', [
            $color->getId() => 'red',
            $theme->getId() => 'application',
            $mode->getId() => 'default',
        ]);
        $domain = $this->makeDomain('example.com', [
            $color->getId() => 'blue',
            $theme->getId() => 'domain',
        ]);
        $subdomain = $this->makeProject('subdomain1', [
            $color->getId() => 'green',
        ]);
        $domain->addProject($subdomain);

        $service = $this->makeService($domain, $subdomain, $application);

        self::assertSame(
            ['colors' => ['primary' => 'green', 'theme' => 'domain', 'mode' => 'default']],
            $service->all(),
        );
    }

    public function testElectronValuesOverlayProjectWhenElectronContextIsLoaded(): void
    {
        $color = $this->makeEnv('primary');
        $theme = $this->makeEnv('theme');

        $application = $this->makeApplication('test', [
            $color->getId() => 'red',
        ]);
        $domain = $this->makeDomain('example.com', [
            $color->getId() => 'blue',
            $theme->getId() => 'domain',
        ]);
        $subdomain = $this->makeProject('subdomain1', [
            $color->getId() => 'green',
        ]);
        $electron = $this->makeElectron('Example Electron', [
            $color->getId() => 'purple',
        ]);
        $domain->addProject($subdomain);

        $service = $this->makeService($domain, $subdomain, $application, $electron);

        self::assertSame(
            ['colors' => ['primary' => 'purple', 'theme' => 'domain']],
            $service->all(),
        );
    }

    public function testGetTrimsLookupIdAndRejectsEmptyLookup(): void
    {
        $env = $this->makeEnv('primary');
        $domain = $this->makeDomain('example.com', [$env->getId() => 'blue']);

        $service = $this->makeService($domain, null);

        self::assertNull($service->get(''));
        self::assertNull($service->get('   '));
        self::assertNull($service->get('primary'));
        self::assertSame('blue', $service->get('  colors.primary  '));
    }

    public function testExplicitProjectEntityIgnoresUnrelatedDomain(): void
    {
        $color = $this->makeEnv('primary');

        $matchedDomain = $this->makeDomain('example.com', [$color->getId() => 'blue']);
        $unmatchedDomain = $this->makeDomain('other.com', [$color->getId() => 'red']);

        $subdomain = $this->makeProject('subdomain1', [$color->getId() => 'green']);
        $matchedDomain->addProject($subdomain);

        // Current request resolves to the UNRELATED domain, but we pass in
        // the Project entity explicitly. EnvService should only use the
        // ambient domain if the subdomain actually belongs to it.
        $service = $this->makeService($unmatchedDomain, null);

        self::assertSame(
            ['colors' => ['primary' => 'green']],
            $service->all($subdomain),
            'unrelated ambient domain must not leak values when an explicit subdomain is passed',
        );
    }

    public function testExplicitDomainIgnoresAmbientProject(): void
    {
        $color = $this->makeEnv('primary');
        $domain = $this->makeDomain('example.com', [$color->getId() => 'blue']);
        $subdomain = $this->makeProject('subdomain1', [$color->getId() => 'green']);
        $domain->addProject($subdomain);

        $service = $this->makeService($domain, $subdomain);

        self::assertSame(
            ['colors' => ['primary' => 'blue']],
            $service->all($domain),
            'passing a Domain explicitly must scope to domain-level env values only',
        );
    }

    public function testSkipsEnvRowsWithNullOrEmptyEnvIds(): void
    {
        $valid = $this->makeEnv('primary');

        $domain = new Domain();
        $domain->setId('example.com');

        // Valid row:
        $good = (new DomainEnv())->setEnv($valid)->setValue('blue');
        $domain->addEnv($good);

        // Row with a null Env (e.g. dangling FK) — should be skipped without crashing.
        $orphan = (new DomainEnv())->setValue('oops');
        $domain->addEnv($orphan);

        // Row with an empty-id Env — also skipped.
        $emptyIdEnv = (new Env())->setEnvParent((new EnvParent())->setId('colors'));
        $reflection = new \ReflectionProperty(Env::class, 'id');
        $reflection->setValue($emptyIdEnv, '');
        $blank = (new DomainEnv())->setEnv($emptyIdEnv)->setValue('nope');
        $domain->addEnv($blank);

        $service = $this->makeService($domain, null);

        self::assertSame(['colors' => ['primary' => 'blue']], $service->all());
    }

    private function makeService(?Domain $domain, ?Project $subdomain, ?Application $application = null, ?Electron $electron = null): EnvService
    {
        $applicationService = $this->createStub(ApplicationService::class);
        $applicationService->method('load')->willReturn($application);

        $domainService = $this->createStub(DomainService::class);
        $domainService->method('load')->willReturn($domain);

        $subdomainService = $this->createStub(ProjectService::class);
        $subdomainService->method('load')->willReturn($subdomain);

        $electronService = $this->createStub(ElectronService::class);
        $electronService->method('load')->willReturn($electron);

        return new EnvService($applicationService, $domainService, $electronService, $subdomainService);
    }

    /**
     * @param array<string, string> $values
     */
    private function makeApplication(string $id, array $values): Application
    {
        $application = (new Application())->setId(str_contains($id, '/') ? $id : 'core/'.$id);

        foreach ($values as $envId => $value) {
            $env = $this->makeEnv($envId);
            $applicationEnv = (new ApplicationEnv())->setEnv($env)->setValue($value);
            $application->addEnv($applicationEnv);
        }

        return $application;
    }

    /**
     * @param array<string, string> $values
     */
    private function makeDomain(string $id, array $values): Domain
    {
        $domain = (new Domain())->setId($id);

        foreach ($values as $envId => $value) {
            $env = $this->makeEnv($envId);
            $domainEnv = (new DomainEnv())->setEnv($env)->setValue($value);
            $domain->addEnv($domainEnv);
        }

        return $domain;
    }

    /**
     * @param array<string, string> $values
     */
    private function makeProject(string $id, array $values): Project
    {
        $subdomain = (new Project())->setId(str_contains($id, '/') ? $id : 'core/'.$id);

        foreach ($values as $envId => $value) {
            $env = $this->makeEnv($envId);
            $subdomainEnv = (new ProjectEnv())->setEnv($env)->setValue($value);
            $subdomain->addEnv($subdomainEnv);
        }

        return $subdomain;
    }

    private function makeEnv(string $id): Env
    {
        return (new Env())
            ->setId($id)
            ->setEnvParent((new EnvParent())->setId('colors'));
    }

    /**
     * @param array<string, string> $values
     */
    private function makeElectron(string $name, array $values): Electron
    {
        $electron = (new Electron())
            ->setId('core/'.strtolower(str_replace(' ', '-', $name)))
            ->setName($name)
            ->setType(Electron::TYPE_DOMAIN);

        foreach ($values as $envId => $value) {
            $env = $this->makeEnv($envId);
            $electronEnv = (new ElectronEnv())->setEnv($env)->setValue($value);
            $electron->addEnv($electronEnv);
        }

        return $electron;
    }
}
