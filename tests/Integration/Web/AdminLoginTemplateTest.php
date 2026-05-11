<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Entity\Admin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Twig\Environment;

final class AdminLoginTemplateTest extends SymfonicatKernelTestCase
{
    public function testLoginFormOptsOutOfTurbo(): void
    {
        $html = $this->renderLoginTemplate([
            'admin' => null,
            'last_username' => '',
            'error' => null,
        ]);

        self::assertMatchesRegularExpression(
            '/<form[^>]+action="\/admin\/login\/check"[^>]+data-turbo="false"/',
            $html,
        );
    }

    public function testMfaFormOptsOutOfTurbo(): void
    {
        $admin = (new Admin())
            ->setEmail('admin@example.com')
            ->setMfaSecret('ABCDEFGHIJKLMNOP');

        $html = $this->renderLoginTemplate([
            'admin' => $admin,
        ]);

        self::assertMatchesRegularExpression(
            '/<form[^>]+action="\/admin\/login"[^>]+data-turbo="false"/',
            $html,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderLoginTemplate(array $context): string
    {
        $requestStack = self::getTestContainer()->get(RequestStack::class);
        $request = Request::create('/admin/login');

        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getTestContainer()->get('session.factory');
        $request->setSession($sessionFactory->createSession());
        $requestStack->push($request);

        /** @var Environment $twig */
        $twig = self::getTestContainer()->get(Environment::class);

        try {
            return $twig->render('@symfonicat/login.html.twig', $context);
        } finally {
            $requestStack->pop();
        }
    }
}
