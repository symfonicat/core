<?php

namespace Symfonicat\Twig;

use Symfonicat\Service\EnvService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class EnvExtension extends AbstractExtension
{
    public function __construct(
        private readonly EnvService $envService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('env', $this->render(...)),
        ];
    }

    public function render(string $id): ?string
    {
        return $this->envService->get($id);
    }
}
