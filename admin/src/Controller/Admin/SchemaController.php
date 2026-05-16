<?php

namespace Symfonicat\Controller\Admin;

use Symfonicat\Service\BundleService;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\ModuleService;
use Symfonicat\Service\SchemaSynchronizer;
use Symfonicat\Service\SubdomainService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SchemaController extends AbstractController
{
    public function __construct(
        private readonly BundleService $bundleService,
        private readonly DomainService $domainService,
        private readonly ModuleService $moduleService,
        private readonly SubdomainService $subdomainService,
        private readonly SchemaSynchronizer $schemaSynchronizer,
    ) {
    }

    #[Route('/admin/s', name: 'symfonicat_admin_schema_update', methods: ['GET'])]
    public function update(Request $request): RedirectResponse
    {
        try {
            $this->schemaSynchronizer->synchronize();

            $bundleResult = $this->bundleService->sync();
            $moduleResult = $this->moduleService->sync();
            $domainResult = $this->domainService->sync();
            $subdomainResult = $this->subdomainService->sync();

            $this->addFlash('success', sprintf(
                'schema synchronized: %s',
                implode(', ', array_filter([
                    $this->countSummary('bundles created', $bundleResult['created']),
                    $this->countSummary('bundles updated', $bundleResult['updated']),
                    $this->countSummary('modules created', $moduleResult['created']),
                    $this->countSummary('modules updated', $moduleResult['updated']),
                    $this->countSummary('modules deleted', $moduleResult['deleted']),
                    $this->countSummary('domains created', $domainResult['created']),
                    $this->countSummary('subdomains created', $subdomainResult['created']),
                ])) ?: 'no package row changes',
            ));
        } catch (\Throwable $exception) {
            $this->addFlash('error', sprintf('schema sync failed: %s', $exception->getMessage()));
        }

        return $this->redirectAfterAction($request);
    }

    /**
     * @param array<int, mixed> $items
     */
    private function countSummary(string $label, array $items): ?string
    {
        $count = count($items);

        return $count > 0 ? sprintf('%d %s', $count, $label) : null;
    }

    private function redirectAfterAction(Request $request): RedirectResponse
    {
        $referer = $request->headers->get('referer');
        $refererPath = is_string($referer) ? parse_url($referer, PHP_URL_PATH) : null;

        if (is_string($referer) && $referer !== '' && $refererPath !== '/admin/s') {
            return $this->redirect($referer, Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('symfonicat_admin_dashboard', [], Response::HTTP_SEE_OTHER);
    }
}
