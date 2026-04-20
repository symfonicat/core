<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\DomainEnv;
use Symfonicat\Entity\Env;
use Symfonicat\Entity\Project;
use Symfonicat\Entity\ProjectEnv;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\EnvService;
use Symfonicat\Service\ProjectService;

/**
 * Pure unit coverage of EnvService — the "env overlay" abstraction the rest of
 * the framework depends on. No container, no database: the service
 * collaborates with two DI-injected services that we stub directly.
 */
final class EnvServiceTest extends TestCase
{
    public function testReturnsEmptyArrayWhenNothingIsLoaded(): void
    {
        $service = $this->makeService(null, null);

        self::assertSame([], $service->all());
        self::assertNull($service->get('color'));
    }

    public function testReturnsDomainValuesWhenOnlyDomainIsLoaded(): void
    {
        $env = $this->makeEnv('color');
        $domain = $this->makeDomain('example.com', [$env->getId() => 'blue']);

        $service = $this->makeService($domain, null);

        self::assertSame(['color' => 'blue'], $service->all());
        self::assertSame('blue', $service->get('color'));
        self::assertNull($service->get('missing'));
    }

    public function testProjectValuesOverlayDomainValuesWhenProjectBelongsToDomain(): void
    {
        $color = $this->makeEnv('color');
        $theme = $this->makeEnv('theme');

        $domain = $this->makeDomain('example.com', [
            $color->getId() => 'blue',
            $theme->getId() => 'default',
        ]);

        $project = $this->makeProject('project1', [
            $color->getId() => 'green',
        ]);

        $domain->addProject($project);

        $service = $this->makeService($domain, $project);

        self::assertSame(
            ['color' => 'green', 'theme' => 'default'],
            $service->all(),
            'project values should overlay matching domain keys; non-overlapping domain keys remain',
        );
        self::assertSame('green', $service->get('color'));
        self::assertSame('default', $service->get('theme'));
    }

    public function testGetTrimsLookupIdAndRejectsEmptyLookup(): void
    {
        $env = $this->makeEnv('color');
        $domain = $this->makeDomain('example.com', [$env->getId() => 'blue']);

        $service = $this->makeService($domain, null);

        self::assertNull($service->get(''));
        self::assertNull($service->get('   '));
        self::assertSame('blue', $service->get('  color  '));
    }

    public function testExplicitProjectEntityIgnoresUnrelatedDomain(): void
    {
        $color = $this->makeEnv('color');

        $matchedDomain = $this->makeDomain('example.com', [$color->getId() => 'blue']);
        $unmatchedDomain = $this->makeDomain('other.com', [$color->getId() => 'red']);

        $project = $this->makeProject('project1', [$color->getId() => 'green']);
        $matchedDomain->addProject($project);

        // Current request resolves to the UNRELATED domain, but we pass in
        // the Project entity explicitly. EnvService should only use the
        // ambient domain if the project actually belongs to it.
        $service = $this->makeService($unmatchedDomain, null);

        self::assertSame(
            ['color' => 'green'],
            $service->all($project),
            'unrelated ambient domain must not leak values when an explicit project is passed',
        );
    }

    public function testExplicitDomainIgnoresAmbientProject(): void
    {
        $color = $this->makeEnv('color');
        $domain = $this->makeDomain('example.com', [$color->getId() => 'blue']);
        $project = $this->makeProject('project1', [$color->getId() => 'green']);
        $domain->addProject($project);

        $service = $this->makeService($domain, $project);

        self::assertSame(
            ['color' => 'blue'],
            $service->all($domain),
            'passing a Domain explicitly must scope to domain-level env values only',
        );
    }

    public function testSkipsEnvRowsWithNullOrEmptyEnvIds(): void
    {
        $valid = $this->makeEnv('color');

        $domain = new Domain();
        $domain->setId('example.com');

        // Valid row:
        $good = (new DomainEnv())->setEnv($valid)->setValue('blue');
        $domain->addEnv($good);

        // Row with a null Env (e.g. dangling FK) — should be skipped without crashing.
        $orphan = (new DomainEnv())->setValue('oops');
        $domain->addEnv($orphan);

        // Row with an empty-id Env — also skipped.
        $emptyIdEnv = new Env();
        $reflection = new \ReflectionProperty(Env::class, 'id');
        $reflection->setValue($emptyIdEnv, '');
        $blank = (new DomainEnv())->setEnv($emptyIdEnv)->setValue('nope');
        $domain->addEnv($blank);

        $service = $this->makeService($domain, null);

        self::assertSame(['color' => 'blue'], $service->all());
    }

    private function makeService(?Domain $domain, ?Project $project): EnvService
    {
        $domainService = $this->createStub(DomainService::class);
        $domainService->method('load')->willReturn($domain);

        $projectService = $this->createStub(ProjectService::class);
        $projectService->method('load')->willReturn($project);

        return new EnvService($domainService, $projectService);
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
        $project = (new Project())->setId($id)->setName(ucfirst($id));

        foreach ($values as $envId => $value) {
            $env = $this->makeEnv($envId);
            $projectEnv = (new ProjectEnv())->setEnv($env)->setValue($value);
            $project->addEnv($projectEnv);
        }

        return $project;
    }

    private function makeEnv(string $id): Env
    {
        return (new Env())->setId($id);
    }
}
