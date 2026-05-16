<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Entity\Bundle;
use Symfonicat\Form\BundleType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

final class BundleFormTest extends SymfonicatKernelTestCase
{
    public function testExistingBundleRendersPathFieldInEditForm(): void
    {
        $bundle = (new Bundle())
            ->setId('core/shared')
            ->setPath('assets/bundles/shared');

        $this->entityManager()->persist($bundle);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $bundle = $this->entityManager()->getRepository(Bundle::class)->find('core/shared');
        self::assertInstanceOf(Bundle::class, $bundle);

        $requestStack = self::getTestContainer()->get(RequestStack::class);
        $request = Request::create('/admin/b/core/shared');

        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getTestContainer()->get('session.factory');
        $request->setSession($sessionFactory->createSession());
        $requestStack->push($request);

        /** @var FormFactoryInterface $formFactory */
        $formFactory = self::getTestContainer()->get(FormFactoryInterface::class);
        $form = $formFactory->create(BundleType::class, $bundle, [
            'id_editable' => false,
        ])->createView();

        /** @var Environment $twig */
        $twig = self::getTestContainer()->get(Environment::class);

        try {
            $html = $twig->render('@symfonicat/bundle/_form.html.twig', [
                'bundle' => $bundle,
                'form' => $form,
                'button_label' => 'save',
            ]);

            self::assertStringContainsString('name="bundle[path]"', $html);
            self::assertMatchesRegularExpression('/name="bundle\\[path\\]"[^>]*disabled/', $html);
            self::assertStringContainsString('assets/bundles/shared', $html);
        } finally {
            $requestStack->pop();
        }
    }
}
