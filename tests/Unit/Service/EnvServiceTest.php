<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\ApplicationEnv;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\DomainEnv;
use Symfonicat\Entity\Env;
use Symfonicat\Entity\EnvParent;
use Symfonicat\Entity\Subdomain;
use Symfonicat\Entity\SubdomainEnv;
use Symfonicat\Service\ApplicationService;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\EnvService;
use Symfonicat\Service\SubdomainService;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Pure unit coverage of EnvService — the "env overlay" abstraction the rest of
 * the framework depends on. No container, no database: the service
 * collaborates with three domain-resolution services plus a request stack
 * that we stub directly.
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

    public function testSubdomainValuesOverlayDomainValuesWhenSubdomainBelongsToDomain(): void
    {
        $color = $this->makeEnv('primary');
        $theme = $this->makeEnv('theme');

        $domain = $this->makeDomain('example.com', [
            $color->getId() => 'blue',
            $theme->getId() => 'default',
        ]);

        $subdomain = $this->makeSubdomain('subdomain1', [
            $color->getId() => 'green',
        ]);

        $domain->addSubdomain($subdomain);

        $service = $this->makeService($domain, $subdomain);

        self::assertSame(
            ['colors' => ['primary' => 'green', 'theme' => 'default']],
            $service->all(),
            'subdomain values should overlay matching domain keys; non-overlapping domain keys remain',
        );
        self::assertSame('green', $service->get('colors.primary'));
        self::assertSame('default', $service->get('colors.theme'));
    }

    public function testSubdomainValuesOverlayDomainAndApplicationValues(): void
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
        $subdomain = $this->makeSubdomain('subdomain1', [
            $color->getId() => 'green',
        ]);
        $domain->addSubdomain($subdomain);

        $service = $this->makeService($domain, $subdomain, $application);

        self::assertSame(
            ['colors' => ['primary' => 'red', 'theme' => 'application', 'mode' => 'default']],
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

    public function testExplicitSubdomainEntityIgnoresUnrelatedDomain(): void
    {
        $color = $this->makeEnv('primary');

        $matchedDomain = $this->makeDomain('example.com', [$color->getId() => 'blue']);
        $unmatchedDomain = $this->makeDomain('other.com', [$color->getId() => 'red']);

        $subdomain = $this->makeSubdomain('subdomain1', [$color->getId() => 'green']);
        $matchedDomain->addSubdomain($subdomain);

        // Current request resolves to the UNRELATED domain, but we pass in
        // the Subdomain entity explicitly. EnvService should only use the
        // ambient domain if the subdomain actually belongs to it.
        $service = $this->makeService($unmatchedDomain, null);

        self::assertSame(
            ['colors' => ['primary' => 'green']],
            $service->all($subdomain),
            'unrelated ambient domain must not leak values when an explicit subdomain is passed',
        );
    }

    public function testExplicitDomainIgnoresAmbientSubdomain(): void
    {
        $color = $this->makeEnv('primary');
        $domain = $this->makeDomain('example.com', [$color->getId() => 'blue']);
        $subdomain = $this->makeSubdomain('subdomain1', [$color->getId() => 'green']);
        $domain->addSubdomain($subdomain);

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

    private function makeService(?Domain $domain, ?Subdomain $subdomain, ?Application $application = null): EnvService
    {
        $applicationService = $this->createStub(ApplicationService::class);
        $applicationService->method('load')->willReturn($application);

        $domainService = $this->createStub(DomainService::class);
        $domainService->method('load')->willReturn($domain);

        $subdomainService = $this->createStub(SubdomainService::class);
        $subdomainService->method('load')->willReturn($subdomain);

        $requestStack = $this->createStub(RequestStack::class);

        return new EnvService($domainService, $applicationService, $subdomainService, $requestStack);
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
    private function makeSubdomain(string $id, array $values): Subdomain
    {
        $subdomain = (new Subdomain())->setId(str_contains($id, '/') ? $id : 'core/'.$id);

        foreach ($values as $envId => $value) {
            $env = $this->makeEnv($envId);
            $subdomainEnv = (new SubdomainEnv())->setEnv($env)->setValue($value);
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

}
