<?php

namespace Symfonicat\Twig;

use Symfonicat\Service\ElectronService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class ElectronExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly ElectronService $electronService,
    ) {
    }

    public function getGlobals(): array
    {
        $electron = $this->electronService->isElectronRequest();
        $electronConfig = $this->electronService->load();
        $electronIcon = $electronConfig?->getFavicon();

        return [
            'electron' => $electron,
            'electron_icon' => (is_string($electronIcon) && $electronIcon !== '') ? $electronIcon : null,
        ];
    }
}
