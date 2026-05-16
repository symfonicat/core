<?php

namespace App\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfonicat\Controller\AbstractModuleController;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Module;
use Symfonicat\Entity\Project;
use Symfonicat\Repository\DomainRepository;
use Symfonicat\Repository\ModuleRepository;
use Symfonicat\Repository\ProjectRepository;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\ModuleService;
use Symfonicat\Service\PackageDiscoveryService;
use Symfonicat\Service\PathService;
use Symfonicat\Service\ProjectService;
use Symfonicat\Service\RuntimeConfig;
use Symfonicat\Service\SubdomainService;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * AbstractModuleController's constructor wires a `$shouldRun` flag that
 * controllers use to decide whether the incoming /m/<module>/... request is
 * allowed for the current domain/project/application context. These tests pin
 * the core branches of that guard so refactoring the base class can't silently expose
 * modules that the owning entity hasn't installed.
 */
final class AbstractModuleControllerTest extends TestCase
{
    public function testProjectWithInstalledModuleAllowsExecution(): void
    {
        $module = $this->makeModule('analytics');
        $project = (new Project())->setId('core/project1');
        $project->addModule($module);

        $controller = $this->makeController(
            domain: $this->makeDomain('example.com'),
            project: $project,
            module: $module,
        );

        $shouldRun = new Response('ran', 200);
        self::assertSame($shouldRun, $controller->runModule($shouldRun));
    }

    public function testProjectWithoutModuleThrowsNotFound(): void
    {
        $module = $this->makeModule('analytics');
        $project = (new Project())->setId('core/project1');

        $controller = $this->makeController(
            domain: $this->makeDomain('example.com'),
            project: $project,
            module: $module,
        );

        $this->expectException(NotFoundHttpException::class);
        $controller->runModule(new Response('must not reach here'));
    }

    public function testDomainWithInstalledModuleAllowsExecutionWhenNoProject(): void
    {
        $module = $this->makeModule('analytics');
        $domain = $this->makeDomain('example.com');
        $domain->addModule($module);

        $controller = $this->makeController(
            domain: $domain,
            project: null,
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
            project: null,
            module: $module,
        );

        $fallback = new Response('nope', 403);
        self::assertSame(
            $fallback,
            $controller->runModule(new Response('must not reach here'), $fallback),
            'passing $shouldNotRunResponse opts out of the 404 branch',
        );
    }

    public function testDomainModuleBranchIsDisabledWhenProjectPresent(): void
    {
        // Module is on the domain, not the project: the project branch takes
        // precedence and the domain fallback must NOT fire.
        $module = $this->makeModule('analytics');
        $domain = $this->makeDomain('example.com');
        $domain->addModule($module);
        $project = (new Project())->setId('core/project1');

        $controller = $this->makeController(
            domain: $domain,
            project: $project,
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
            project: null,
            module: null,
        );

        $this->expectException(NotFoundHttpException::class);
        $controller->runModule(new Response('must not reach here'));
    }

    private function makeController(?Domain $domain, ?Project $project, ?Module $module): object
    {
        $projectDir = dirname(__DIR__, 3);
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/m/symfonicat/analytics/main', 'POST', [], [], [], [
            'HTTP_HOST' => $this->makeHost($domain, $project),
        ]));

        $domainRepository = $this->createStub(DomainRepository::class);
        $domainRepository->method('find')->willReturn($domain);
        $domainRepository->method('findOneByHost')->willReturn($domain);
        $packageDiscoveryService = new PackageDiscoveryService($projectDir);
        $runtimeConfig = new RuntimeConfig($projectDir);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $domainService = new DomainService($projectDir, $requestStack, $domainRepository, $entityManager, $packageDiscoveryService, $runtimeConfig);

        $projectRepository = $this->createStub(ProjectRepository::class);
        $projectRepository->method('find')->willReturn($project);
        $projectRepository->method('findOneByIdForDomain')->willReturn($project);
        $subdomainService = new SubdomainService($projectDir, $requestStack, new NullLogger(), $domainService);
        $projectService = new ProjectService(
            $domainService,
            $subdomainService,
            $projectRepository,
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

        return new class($domainService, $moduleService, $projectService, $pathService) extends AbstractModuleController {
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

    private function makeHost(?Domain $domain, ?Project $project): string
    {
        $domainId = $domain?->getId(false) ?? 'example.com';

        if ($project instanceof Project && $project->getId(false) !== null) {
            return $project->getId(false).'.'.$domainId;
        }

        return $domainId;
    }
}
