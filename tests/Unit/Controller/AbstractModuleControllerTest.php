<?php

namespace App\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfonicat\Controller\AbstractModuleController;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Module;
use Symfonicat\Entity\Subdomain;
use Symfonicat\Repository\DomainRepository;
use Symfonicat\Repository\ModuleRepository;
use Symfonicat\Repository\SubdomainRepository;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\ModuleService;
use Symfonicat\Service\PackageDiscoveryService;
use Symfonicat\Service\PathService;
use Symfonicat\Service\SubdomainService;
use Symfonicat\Service\RuntimeConfig;
use Symfonicat\Service\AffixService;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * AbstractModuleController's constructor wires a `$shouldRun` flag that
 * controllers use to decide whether the incoming /m/<module>/... request is
 * allowed for the current domain/subdomain/application context. These tests pin
 * the core branches of that guard so refactoring the base class can't silently expose
 * modules that the owning entity hasn't installed.
 */
final class AbstractModuleControllerTest extends TestCase
{
    public function testSubdomainWithInstalledModuleAllowsExecution(): void
    {
        $module = $this->makeModule('analytics');
        $subdomain = (new Subdomain())->setId('core/subdomain1');
        $subdomain->addModule($module);

        $controller = $this->makeController(
            domain: $this->makeDomain('example.com'),
            subdomain: $subdomain,
            module: $module,
        );

        $shouldRun = new Response('ran', 200);
        self::assertSame($shouldRun, $controller->runModule($shouldRun));
    }

    public function testSubdomainWithoutModuleThrowsNotFound(): void
    {
        $module = $this->makeModule('analytics');
        $subdomain = (new Subdomain())->setId('core/subdomain1');

        $controller = $this->makeController(
            domain: $this->makeDomain('example.com'),
            subdomain: $subdomain,
            module: $module,
        );

        $this->expectException(NotFoundHttpException::class);
        $controller->runModule(new Response('must not reach here'));
    }

    public function testDomainWithInstalledModuleAllowsExecutionWhenNoSubdomain(): void
    {
        $module = $this->makeModule('analytics');
        $domain = $this->makeDomain('example.com');
        $domain->addModule($module);

        $controller = $this->makeController(
            domain: $domain,
            subdomain: null,
            module: $module,
        );

        $shouldRun = new Response('ran');
        self::assertSame($shouldRun, $controller->runModule($shouldRun));
    }

    public function testDomainWithoutModuleFallsBackToShouldNotRunResponse(): void
    {
        $module = $this->makeModule('analytics');
        $domain = $this->makeDomain('example.com');

        $controller = $this->makeController(
            domain: $domain,
            subdomain: null,
            module: $module,
        );

        $fallback = new Response('nope', 403);
        self::assertSame(
            $fallback,
            $controller->runModule(new Response('must not reach here'), $fallback),
            'passing $shouldNotRunResponse opts out of the 404 branch',
        );
    }

    public function testDomainModuleBranchIsDisabledWhenSubdomainPresent(): void
    {
        // Module is on the domain, not the subdomain: the subdomain branch takes
        // precedence and the domain fallback must NOT fire.
        $module = $this->makeModule('analytics');
        $domain = $this->makeDomain('example.com');
        $domain->addModule($module);
        $subdomain = (new Subdomain())->setId('core/subdomain1');

        $controller = $this->makeController(
            domain: $domain,
            subdomain: $subdomain,
            module: $module,
        );

        $this->expectException(NotFoundHttpException::class);
        $controller->runModule(new Response('must not reach here'));
    }

    public function testMissingModuleAlwaysBlocks(): void
    {
        $domain = $this->makeDomain('example.com');
        $controller = $this->makeController(
            domain: $domain,
            subdomain: null,
            module: null,
        );

        $this->expectException(NotFoundHttpException::class);
        $controller->runModule(new Response('must not reach here'));
    }

    public function testUnvalidatedModuleRequestAlwaysBlocks(): void
    {
        $module = $this->makeModule('analytics');
        $domain = $this->makeDomain('example.com');
        $domain->addModule($module);

        $controller = $this->makeController(
            domain: $domain,
            subdomain: null,
            module: $module,
            validatedModuleRequest: false,
        );

        $this->expectException(NotFoundHttpException::class);
        $controller->runModule(new Response('must not reach here'));
    }

    private function makeController(?Domain $domain, ?Subdomain $subdomain, ?Module $module, bool $validatedModuleRequest = true): object
    {
        $subdomainDir = dirname(__DIR__, 3);
        $requestStack = new RequestStack();
        $request = Request::create('/m/symfonicat/analytics/main', 'POST', [], [], [], [
            'HTTP_HOST' => $this->makeHost($domain, $subdomain),
        ]);
        $requestStack->push($request);
        if ($validatedModuleRequest) {
            $requestStack->getCurrentRequest()?->attributes->set('symfonicat_module_request_valid', true);
        }

        $domainRepository = $this->createStub(DomainRepository::class);
        $domainRepository->method('find')->willReturn($domain);
        $domainRepository->method('findOneByHost')->willReturn($domain);
        $packageDiscoveryService = new PackageDiscoveryService($subdomainDir);
        $runtimeConfig = new RuntimeConfig($subdomainDir);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $domainService = new DomainService($subdomainDir, $requestStack, $domainRepository, $entityManager, $packageDiscoveryService, $runtimeConfig);

        $subdomainRepository = $this->createStub(SubdomainRepository::class);
        $subdomainRepository->method('find')->willReturn($subdomain);
        $subdomainRepository->method('findOneByIdForDomain')->willReturn($subdomain);
        $affixService = new AffixService($subdomainDir, $requestStack, new NullLogger(), $domainService);
        $subdomainService = new SubdomainService(
            $domainService,
            $affixService,
            $subdomainRepository,
            $entityManager,
            $packageDiscoveryService,
            $runtimeConfig,
        );

        $pathService = new PathService($requestStack);
        $moduleRepository = $this->createStub(ModuleRepository::class);
        $moduleRepository->method('find')->willReturn($module);
        $moduleRepository->method('findOneByFullOrCleanId')->willReturn($module);
        $moduleService = new ModuleService(
            $requestStack,
            $pathService,
            $moduleRepository,
            $entityManager,
            $packageDiscoveryService,
            $runtimeConfig,
        );

        return new class($domainService, $moduleService, $subdomainService, $pathService, new NullLogger(), $requestStack) extends AbstractModuleController {
            public function runModule(Response $shouldRun, Response|false $fallback = false): Response
            {
                return $this->module($shouldRun, $fallback);
            }
        };
    }

    private function makeModule(string $id): Module
    {
        return (new Module())->setId(str_contains($id, '/') ? $id : 'symfonicat/'.$id.'/main');
    }

    private function makeDomain(string $id): Domain
    {
        return (new Domain())->setId($id);
    }

    private function makeHost(?Domain $domain, ?Subdomain $subdomain): string
    {
        $domainId = $domain?->getId(false) ?? 'example.com';

        if ($subdomain instanceof Subdomain && $subdomain->getId(false) !== null) {
            return $subdomain->getId(false).'.'.$domainId;
        }

        return $domainId;
    }
}
