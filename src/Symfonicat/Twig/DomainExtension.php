<?php

namespace Symfonicat\Twig;

use Symfonicat\Service\DomainService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class DomainExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly DomainService $domainService,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getGlobals(): array
    {
        if (null === $this->requestStack->getCurrentRequest()) {
            return ['domain' => null];
        }

        return ['domain' => $this->domainService->load()];
    }
}
