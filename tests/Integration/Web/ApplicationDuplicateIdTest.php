<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatWebTestCase;
use Symfonicat\Controller\Admin\ApplicationController;
use Symfonicat\Entity\RoutingRule;
use Symfonicat\Repository\ApplicationRepository;
use Symfonicat\Repository\EnvParentRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;

final class ApplicationDuplicateIdTest extends SymfonicatWebTestCase
{
    public function testApplicationLookupByCleanIdThrowsWhenMultipleFullIdsMatch(): void
    {
        $this->createApplication('core/test');
        $this->createApplication('superman/test');

        /** @var ApplicationRepository $repository */
        $repository = self::getTestContainer()->get(ApplicationRepository::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Application id "test" is ambiguous.');

        $repository->findOneByFullOrCleanId('test');
    }

    public function testAdminApplicationListFlashesDuplicateIdWarning(): void
    {
        $application = $this->createApplication('core/test');
        $duplicate = $this->createApplication('superman/test');
        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplication($application)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_ROUTE)
            ->setRoute('symfonicat_subdomain_test');
        $duplicateRule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplication($duplicate)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_ROUTE)
            ->setRoute('symfonicat_subdomain_test');

        $this->entityManager()->persist($rule);
        $this->entityManager()->persist($duplicateRule);
        $this->entityManager()->flush();

        $requestStack = self::getTestContainer()->get(RequestStack::class);
        $request = Request::create('/admin/a/list');

        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getTestContainer()->get('session.factory');
        $request->setSession($sessionFactory->createSession());
        $requestStack->push($request);

        /** @var ApplicationController $controller */
        $controller = self::getTestContainer()->get(ApplicationController::class);
        /** @var ApplicationRepository $applicationRepository */
        $applicationRepository = self::getTestContainer()->get(ApplicationRepository::class);
        /** @var EnvParentRepository $envParentRepository */
        $envParentRepository = self::getTestContainer()->get(EnvParentRepository::class);

        try {
            $response = $controller->index($applicationRepository, $envParentRepository);
            $html = (string) $response->getContent();

            self::assertStringContainsString('alert alert-danger', $html);
            self::assertStringContainsString(
                'duplicate application ids detected: test: core/test, superman/test',
                $html,
            );
        } finally {
            $requestStack->pop();
        }
    }
}
