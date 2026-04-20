<?php

namespace App\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use Symfonicat\Controller\AbstractModuleController;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Module;
use Symfonicat\Entity\Project;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\ModuleService;
use Symfonicat\Service\PathService;
use Symfonicat\Service\ProjectService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * AbstractModuleController's constructor wires a `$shouldRun` flag that
 * controllers use to decide whether the incoming /m/<module>/... request is
 * allowed for the current domain/project context. These tests pin the four
 * branches of that guard so refactoring the base class can't silently expose
 * modules that the owning entity hasn't installed.
 */
final class AbstractModuleControllerTest extends TestCase
{
    public function testProjectWithInstalledModuleAllowsExecution(): void
    {
        $module = $this->makeModule('analytics');
        $project = (new Project())->setId('project1');
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
        $project = (new Project())->setId('project1');

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
        $project = (new Project())->setId('project1');

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
        $domainService = $this->createStub(DomainService::class);
        $domainService->method('load')->willReturn($domain);

        $projectService = $this->createStub(ProjectService::class);
        $projectService->method('load')->willReturn($project);

        $moduleService = $this->createStub(ModuleService::class);
        $moduleService->method('load')->willReturn($module);

        $pathService = $this->createStub(PathService::class);

        return new class($domainService, $moduleService, $projectService, $pathService) extends AbstractModuleController {
            public function runModule(Response $shouldRun, Response|false $fallback = false): Response
            {
                return $this->module($shouldRun, $fallback);
            }
        };
    }

    private function makeModule(string $id): Module
    {
        return (new Module())->setId($id)->setName(ucfirst($id));
    }

    private function makeDomain(string $id): Domain
    {
        return (new Domain())->setId($id);
    }
}
