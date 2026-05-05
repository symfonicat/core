<?php

namespace Symfonicat\Twig;

use Symfonicat\Entity\Project;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class ProjectExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getGlobals(): array
    {
        if (null === $this->requestStack->getCurrentRequest()) {
            return [
                'project' => null,
            ];
        }

        $project = $this->requestStack->getCurrentRequest()?->attributes->get('project');

        return ['project' => $project instanceof Project ? $project : null];
    }
}
