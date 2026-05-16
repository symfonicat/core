<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatWebTestCase;
use Symfonicat\Controller\Admin\SubdomainController;
use Symfonicat\Repository\EnvParentRepository;
use Symfonicat\Repository\SubdomainRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;

final class SubdomainDuplicateIdTest extends SymfonicatWebTestCase
{
    public function testSubdomainLookupByCleanIdThrowsWhenMultipleFullIdsMatch(): void
    {
        $this->createSubdomain('core/subdomain1');
        $this->createSubdomain('superman/subdomain1');

        /** @var SubdomainRepository $repository */
        $repository = self::getTestContainer()->get(SubdomainRepository::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Subdomain id "subdomain1" is ambiguous.');

        $repository->findOneByFullOrCleanId('subdomain1');
    }

    public function testAdminSubdomainListFlashesDuplicateIdWarning(): void
    {
        $this->createSubdomain('core/subdomain1');
        $this->createSubdomain('superman/subdomain1');

        $requestStack = self::getTestContainer()->get(RequestStack::class);
        $request = Request::create('/admin/p/list');

        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getTestContainer()->get('session.factory');
        $request->setSession($sessionFactory->createSession());
        $requestStack->push($request);

        /** @var SubdomainController $controller */
        $controller = self::getTestContainer()->get(SubdomainController::class);
        /** @var SubdomainRepository $subdomainRepository */
        $subdomainRepository = self::getTestContainer()->get(SubdomainRepository::class);
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
