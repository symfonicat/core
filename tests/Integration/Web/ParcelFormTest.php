<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Entity\Parcel;
use Symfonicat\Form\ParcelType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

final class ParcelFormTest extends SymfonicatKernelTestCase
{
    public function testExistingParcelRendersPathFieldInEditForm(): void
    {
        $parcel = (new Parcel())
            ->setId('core/shared')
            ->setPath('assets/parcels/shared');

        $this->entityManager()->persist($parcel);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $parcel = $this->entityManager()->getRepository(Parcel::class)->find('core/shared');
        self::assertInstanceOf(Parcel::class, $parcel);

        $requestStack = self::getTestContainer()->get(RequestStack::class);
        $request = Request::create('/core/b/core/shared');

        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getTestContainer()->get('session.factory');
        $request->setSession($sessionFactory->createSession());
        $requestStack->push($request);

        /** @var FormFactoryInterface $formFactory */
        $formFactory = self::getTestContainer()->get(FormFactoryInterface::class);
        $form = $formFactory->create(ParcelType::class, $parcel, [
            'id_editable' => false,
        ])->createView();

        /** @var Environment $twig */
        $twig = self::getTestContainer()->get(Environment::class);

        try {
            $html = $twig->render('@symfonicat/parcel/_form.html.twig', [
                'parcel' => $parcel,
                'form' => $form,
                'button_label' => 'save',
            ]);

            self::assertStringNotContainsString('name="parcel[path]"', $html);
        } finally {
            $requestStack->pop();
        }
    }
}
