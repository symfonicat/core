<?php

namespace Symfonicat\Twig;

use Symfonicat\Service\ApplicationService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;
use Symfonicat\Entity\Application;

final class ApplicationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly ApplicationService $applicationService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('path_application', $this->pathApplication(...)),
        ];
    }

    public function getGlobals(): array
    {
        return [
            'application' => $this->applicationService->load(),
        ];
    }

    public function pathApplication(Application|string|null $application = null, mixed ...$arguments): string
    {
        $application = $application instanceof Application
            ? $application
            : $this->applicationService->find((string) $application);

        if (!$application instanceof Application) {
            return '/';
        }

        $extraPath = '';
        $wildcards = [];

        foreach ($arguments as $argument) {
            if (is_string($argument)) {
                $extraPath = trim($argument, " \t\n\r\0\x0B/");

                continue;
            }

            if (is_array($argument)) {
                $wildcards = array_values(array_map(static fn (mixed $value): string => trim((string) $value, " \t\n\r\0\x0B/"), $argument));
            }
        }

        $path = [];
        if ($application->isEndpointType() && $application->getEndpoint() !== null) {
            foreach ($application->getEndpoint()->getArguments() as $segment) {
                $path[] = $segment === '*' ? (array_shift($wildcards) ?? '*') : $segment;
            }
        }

        if ($extraPath !== '') {
            array_push($path, ...array_values(array_filter(explode('/', $extraPath), static fn (string $part): bool => $part !== '')));
        }

        return '/'.implode('/', array_map(rawurlencode(...), $path));
    }
}
