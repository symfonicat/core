<?php

namespace Symfonicat\Twig;

use Symfonicat\Service\EnvService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

final class EnvExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly EnvService $envService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('env', $this->render(...)),
            new TwigFunction('env_json', $this->renderJson(...), ['is_safe' => ['html']]),
            new TwigFunction('env_helper', $this->renderHelper(...), ['is_safe' => ['html']]),
        ];
    }

    public function getGlobals(): array
    {
        return [
            'env' => $this->envService->all(),
        ];
    }

    public function render(string $id): ?string
    {
        return $this->envService->get($id);
    }

    public function renderJson(): string
    {
        return json_encode(
            $this->envService->all(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    public function renderHelper(): string
    {
        return json_encode(
            $this->envService->all(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
