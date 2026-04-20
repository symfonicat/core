<?php

namespace Symfonicat\Twig;

use Symfonicat\Repository\ModuleRepository;
use Symfonicat\Service\ProjectService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class ProjectExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly ProjectService $projectService,
        private readonly RequestStack $requestStack,
        private readonly ModuleRepository $moduleRepository,
    ) {
    }

    public function getGlobals(): array
    {
        if (null === $this->requestStack->getCurrentRequest()) {
            return [
                'project' => null,
            ];
        }

        $project = $this->projectService->load();

        return ['project' => $project];
    }
}
