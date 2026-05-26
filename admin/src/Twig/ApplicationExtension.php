<?php

namespace Symfonicat\Twig;

use Symfonicat\Service\ApplicationService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class ApplicationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly ApplicationService $applicationService,
    ) {
    }

    public function getFunctions(): array
    {
        return [];
    }

    public function getGlobals(): array
    {
        return [
            'application' => $this->applicationService->load(),
        ];
    }
}
