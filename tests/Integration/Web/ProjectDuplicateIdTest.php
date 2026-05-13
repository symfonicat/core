<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatWebTestCase;
use Symfonicat\Controller\Admin\ProjectController;
use Symfonicat\Repository\EnvParentRepository;
use Symfonicat\Repository\ProjectRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;

final class ProjectDuplicateIdTest extends SymfonicatWebTestCase
{
    public function testProjectLookupByCleanIdThrowsWhenMultipleFullIdsMatch(): void
    {
        $this->createProject('core/project1');
        $this->createProject('superman/project1');

        /** @var ProjectRepository $repository */
        $repository = self::getTestContainer()->get(ProjectRepository::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Project id "project1" is ambiguous.');

        $repository->findOneByFullOrCleanId('project1');
    }

    public function testAdminProjectListFlashesDuplicateIdWarning(): void
    {
        $this->createProject('core/project1');
        $this->createProject('superman/project1');

        $requestStack = self::getTestContainer()->get(RequestStack::class);
        $request = Request::create('/admin/p/list');

        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getTestContainer()->get('session.factory');
        $request->setSession($sessionFactory->createSession());
        $requestStack->push($request);

        /** @var ProjectController $controller */
        $controller = self::getTestContainer()->get(ProjectController::class);
        /** @var ProjectRepository $projectRepository */
        $projectRepository = self::getTestContainer()->get(ProjectRepository::class);
        /** @var EnvParentRepository $envParentRepository */
        $envParentRepository = self::getTestContainer()->get(EnvParentRepository::class);

        try {
            $response = $controller->index($projectRepository, $envParentRepository);
            $html = (string) $response->getContent();

            self::assertStringContainsString('alert alert-danger', $html);
            self::assertStringContainsString(
                'duplicate project ids detected: project1: core/project1, superman/project1',
                $html,
            );
        } finally {
            $requestStack->pop();
        }
    }
}
