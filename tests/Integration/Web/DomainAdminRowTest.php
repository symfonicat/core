<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Entity\Domain;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Twig\Environment;

final class DomainAdminRowTest extends SymfonicatKernelTestCase
{
    public function testRowDisplaysCleanDomainIdButKeepsFullIdForAdminRoutes(): void
    {
        $domain = $this->createDomain('example.com');

        $html = $this->renderRow($domain);

        self::assertStringContainsString('href="http://example.com"', $html);
        self::assertStringContainsString('>example.com</a>', $html);
        self::assertStringContainsString('href="/admin/d/example.com/edit"', $html);
        self::assertStringContainsString('action="/admin/d/example.com"', $html);
        self::assertStringNotContainsString('core/example.com', $html);
    }

    private function renderRow(Domain $domain): string
    {
        $requestStack = self::getTestContainer()->get(RequestStack::class);
        $request = Request::create('/admin/d/list');

        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getTestContainer()->get('session.factory');
        $request->setSession($sessionFactory->createSession());
        $requestStack->push($request);

        /** @var Environment $twig */
        $twig = self::getTestContainer()->get(Environment::class);

        try {
            return $twig->render('@symfonicat/domain/_row.html.twig', [
                'domain' => $domain,
                'env_parents' => [],
            ]);
        } finally {
            $requestStack->pop();
        }
    }
}
