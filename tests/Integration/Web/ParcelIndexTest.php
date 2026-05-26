<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Entity\Parcel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Twig\Environment;

final class ParcelIndexTest extends SymfonicatKernelTestCase
{
    public function testIndexSplitsParcelIdIntoBucketAndBaseNameColumns(): void
    {
        $parcels = [
            (new Parcel())
                ->setId('core/subdomainparcel')
                ->setPath('assets/parcels/subdomain'),
            (new Parcel())
                ->setId('symfonicat/analytics/parcel2')
                ->setPath('assets/parcels/analytics'),
        ];

        $html = $this->renderIndex($parcels);
        $normalized = preg_replace('/\s+/', '', $html);
        self::assertIsString($normalized);

        self::assertStringContainsString('<thclass="text-end">bucket</th><thclass="text-center">&nbsp;</th><th>parcel</th><th>path</th>', $normalized);
        self::assertStringContainsString(
            '<tdclass="fw-semiboldtext-end">core</td><tdclass="fw-semiboldtext-center"><iclass="fafa-angle-right"></i></td><td>subdomainparcel</td><td><code>assets/parcels/subdomain</code></td>',
            $normalized,
        );
        self::assertStringContainsString(
            '<tdclass="fw-semiboldtext-end">symfonicat/analytics</td><tdclass="fw-semiboldtext-center"><iclass="fafa-angle-right"></i></td><td>parcel2</td><td><code>assets/parcels/analytics</code></td>',
            $normalized,
        );
        self::assertStringNotContainsString('core/subdomainparcel</td><tdclass="fw-semibold">core/subdomainparcel', $normalized);
        self::assertStringNotContainsString('symfonicat/analytics/parcel2</td><tdclass="fw-semibold">symfonicat/analytics/parcel2', $normalized);
    }

    /**
     * @param list<Parcel> $parcels
     */
    private function renderIndex(array $parcels): string
    {
        $requestStack = self::getTestContainer()->get(RequestStack::class);
        $request = Request::create('/admin/p');

        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getTestContainer()->get('session.factory');
        $request->setSession($sessionFactory->createSession());
        $requestStack->push($request);

        /** @var Environment $twig */
        $twig = self::getTestContainer()->get(Environment::class);

        try {
            return $twig->render('@symfonicat/parcel/index.html.twig', [
                'parcels' => $parcels,
                'env_parents' => [],
            ]);
        } finally {
            $requestStack->pop();
        }
    }
}
