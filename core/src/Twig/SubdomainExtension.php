<?php

namespace Symfonicat\Twig;

use Symfonicat\Repository\ModuleRepository;
use Symfonicat\Service\SubdomainService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class SubdomainExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly SubdomainService $subdomainService,
        private readonly RequestStack $requestStack,
        private readonly ModuleRepository $moduleRepository,
    ) {
    }

    public function getGlobals(): array
    {
        if (null === $this->requestStack->getCurrentRequest()) {
            return [
                'subdomain' => null,
            ];
        }

        $subdomain = $this->subdomainService->load();

        return ['subdomain' => $subdomain];
    }
}
