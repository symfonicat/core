<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Entity\Middleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Twig\Environment;

final class MiddlewareIndexTest extends SymfonicatKernelTestCase
{
    public function testIndexRendersIdFirstAndShortClassNamesOnly(): void
    {
        $middlewares = [
            (new Middleware())
                ->setId('core/DomainMiddleware')
                ->setClass('Symfonicat\\Middleware\\DomainMiddleware'),
            (new Middleware())
                ->setId('symfonicat/analytics/AnalyticsMiddleware')
                ->setClass('Symfonicat\\Middleware\\AnalyticsMiddleware'),
        ];

        $html = $this->renderIndex($middlewares);

        $normalized = preg_replace('/\s+/', '', $html);
        self::assertIsString($normalized);

        self::assertStringContainsString('<thclass="text-end"><iclass="fafa-hashtag"></i></th><thclass="text-center">&nbsp;</th><th>class</th>', $normalized);
        self::assertStringContainsString(
            '<tdclass="fw-semiboldtext-end">core</td><tdclass="text-center"><iclass="fafa-angle-right"></i></td><td><code>DomainMiddleware</code></td>',
            $normalized,
        );
        self::assertStringContainsString(
            '<tdclass="fw-semiboldtext-end">symfonicat/analytics</td><tdclass="text-center"><iclass="fafa-angle-right"></i></td><td><code>AnalyticsMiddleware</code></td>',
            $normalized,
        );
        self::assertStringNotContainsString('Symfonicat\\Middleware\\DomainMiddleware', $html);
        self::assertStringNotContainsString('Symfonicat\\Middleware\\AnalyticsMiddleware', $html);
        self::assertStringNotContainsString('symfonicat_middleware_edit', $html);
    }

    /**
     * @param list<Middleware> $middlewares
     */
    private function renderIndex(array $middlewares): string
    {
        $requestStack = self::getTestContainer()->get(RequestStack::class);
        $request = Request::create('/core/m');

        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getTestContainer()->get('session.factory');
        $request->setSession($sessionFactory->createSession());
        $requestStack->push($request);

        /** @var Environment $twig */
        $twig = self::getTestContainer()->get(Environment::class);

        try {
            return $twig->render('@symfonicat/middleware/index.html.twig', [
                'middlewares' => $middlewares,
            ]);
        } finally {
            $requestStack->pop();
        }
    }
}
