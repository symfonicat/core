<?php

namespace Symfonicat\Twig;

use Symfonicat\Entity\Domain;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class DomainExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getGlobals(): array
    {
        if (null === $this->requestStack->getCurrentRequest()) {
            return ['domain' => null];
        }

        $domain = $this->requestStack->getCurrentRequest()?->attributes->get('domain');

        return ['domain' => $domain instanceof Domain ? $domain : null];
    }
}
