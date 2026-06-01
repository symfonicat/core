<?php

namespace App\Tests\Integration\Controller\Core;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Contract\AdminYamlDumper;
use Symfonicat\Controller\Core\SchemaController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;

final class SchemaControllerTest extends SymfonicatKernelTestCase
{
    public function testUpdateDumpsYamlAfterSuccessfulSync(): void
    {
        $requestStack = self::getTestContainer()->get(RequestStack::class);
        $request = Request::create('/core/u');

        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getTestContainer()->get('session.factory');
        $request->setSession($sessionFactory->createSession());
        $requestStack->push($request);

        $state = new class {
            public bool $dumped = false;
        };
        $adminYaml = new class($state) implements AdminYamlDumper {
            public function __construct(private object $state)
            {
            }

            public function dump(): array
            {
                $this->state->dumped = true;

                return [];
            }
        };

        $controller = new SchemaController(
            self::getTestContainer()->get('Symfonicat\Service\ParcelService'),
            self::getTestContainer()->get('Symfonicat\Service\DomainService'),
            self::getTestContainer()->get('Symfonicat\Service\ModuleService'),
            self::getTestContainer()->get('Symfonicat\Service\SubdomainService'),
            self::getTestContainer()->get('Symfonicat\Service\SchemaSynchronizer'),
            $adminYaml,
        );
        $controller->setContainer(self::getTestContainer());

        try {
            $response = $controller->update($request);
        } finally {
            $requestStack->pop();
        }

        self::assertTrue($state->dumped);
        self::assertSame(303, $response->getStatusCode());
    }
}
