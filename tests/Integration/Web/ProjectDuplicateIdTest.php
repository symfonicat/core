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
        $this->createProject('core/subdomain1');
        $this->createProject('superman/subdomain1');

        /** @var ProjectRepository $repository */
        $repository = self::getTestContainer()->get(ProjectRepository::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Project id "subdomain1" is ambiguous.');

        $repository->findOneByFullOrCleanId('subdomain1');
    }

    public function testAdminProjectListFlashesDuplicateIdWarning(): void
    {
        $this->createProject('core/subdomain1');
        $this->createProject('superman/subdomain1');

        $requestStack = self::getTestContainer()->get(RequestStack::class);
        $request = Request::create('/admin/p/list');

        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getTestContainer()->get('session.factory');
        $request->setSession($sessionFactory->createSession());
        $requestStack->push($request);

        /** @var ProjectController $controller */
        $controller = self::getTestContainer()->get(ProjectController::class);
        /** @var ProjectRepository $subdomainRepository */
        $subdomainRepository = self::getTestContainer()->get(ProjectRepository::class);
        /** @var EnvParentRepository $envParentRepository */
        $envParentRepository = self::getTestContainer()->get(EnvParentRepository::class);

        try {
            $response = $controller->index($subdomainRepository, $envParentRepository);
            $html = (string) $response->getContent();

            self::assertStringContainsString('alert alert-danger', $html);
            self::assertStringContainsString(
                'duplicate subdomain ids detected: subdomain1: core/subdomain1, superman/subdomain1',
                $html,
            );
        } finally {
            $requestStack->pop();
        }
    }
}
