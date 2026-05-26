<?php

namespace Symfonicat\Twig;

use Symfonicat\Service\ApplicationService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

final class ApplicationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly ApplicationService $applicationService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('application_helper', $this->renderHelper(...), ['is_safe' => ['html']]),
        ];
    }

    public function getGlobals(): array
    {
        return [
            'application' => $this->applicationService->load(),
        ];
    }

    public function renderHelper(): string
    {
        $application = $this->applicationService->load();

        return json_encode(
            $application ? [
                'id' => $application->getId(),
                'name' => $application->getName(),
                'type' => $application->getType(),
            ] : null,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
