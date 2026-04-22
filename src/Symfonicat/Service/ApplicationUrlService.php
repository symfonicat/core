<?php

namespace Symfonicat\Service;

use Symfonicat\Entity\Application;
use Symfonicat\Entity\RoutingRule;
use Symfonicat\Repository\RoutingRuleRepository;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;

final class ApplicationUrlService
{
    public function __construct(
        private readonly RoutingRuleRepository $routingRuleRepository,
    ) {
    }

    /**
     * @param string|array<int, mixed>|null $path
     * @param array<int, mixed> $arguments
     */
    public function path(string $applicationId, string|array|null $path = null, array $arguments = []): string
    {
        if (is_array($path)) {
            $arguments = $path;
            $path = null;
        }

        $applicationId = trim($applicationId);
        if ($applicationId === '') {
            throw new MissingMandatoryParametersException('The "id" parameter is required for the "symfonicat_application" route.');
        }

        $rule = $this->getRuleForApplicationId($applicationId);
        if (!$rule instanceof RoutingRule) {
            throw new InvalidParameterException(sprintf('Application "%s" does not have an application routing rule.', $applicationId));
        }

        return $this->pathFromRule($rule, (string) $path, $arguments);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function pathFromRouteParameters(array $parameters): string
    {
        $id = (string) ($parameters['id'] ?? '');
        $path = $parameters['path'] ?? null;
        $arguments = $parameters['arguments'] ?? [];

        if (is_array($path) && $arguments === []) {
            $arguments = $path;
            $path = null;
        }

        if (!is_array($arguments)) {
            $arguments = [$arguments];
        }

        return $this->path($id, is_array($path) ? null : (string) ($path ?? ''), array_values($arguments));
    }

    public function getRuleForApplication(Application|string $application): ?RoutingRule
    {
        $applicationId = $application instanceof Application ? (string) $application->getId() : $application;

        return $this->getRuleForApplicationId($applicationId);
    }

    /**
     * @param array<int, mixed> $arguments
     */
    public function pathFromRule(RoutingRule $rule, string $path = '', array $arguments = []): string
    {
        $replacementArguments = array_values(array_map(
            static fn (mixed $argument): string => trim((string) $argument, " \t\n\r\0\x0B/"),
            $arguments,
        ));

        $segments = [];

        foreach ($rule->getArguments() as $argument) {
            $argument = trim($argument, " \t\n\r\0\x0B/");
            if ($argument === '') {
                continue;
            }

            if ($argument === '*') {
                $replacement = array_shift($replacementArguments);
                $segments[] = $replacement === null || $replacement === '' ? '*' : $replacement;

                continue;
            }

            $argument = rtrim($argument, '*');
            if ($argument !== '') {
                $segments[] = $argument;
            }
        }

        foreach ($this->pathSegments($path) as $pathSegment) {
            $segments[] = $pathSegment;
        }

        return $this->segmentsToPath($segments);
    }

    private function getRuleForApplicationId(string $applicationId): ?RoutingRule
    {
        $applicationId = trim($applicationId);
        if ($applicationId === '') {
            return null;
        }

        return $this->routingRuleRepository->findOneTypeApplicationByApplicationId($applicationId);
    }

    /**
     * @return list<string>
     */
    private function pathSegments(string $path): array
    {
        $path = trim($path, " \t\n\r\0\x0B/");
        if ($path === '') {
            return [];
        }

        return array_values(array_filter(
            explode('/', $path),
            static fn (string $segment): bool => $segment !== '',
        ));
    }

    /**
     * @param list<string> $segments
     */
    private function segmentsToPath(array $segments): string
    {
        $segments = array_values(array_filter(
            array_map(
                static fn (string $segment): string => trim($segment, " \t\n\r\0\x0B/"),
                $segments,
            ),
            static fn (string $segment): bool => $segment !== '',
        ));

        if ($segments === []) {
            return '/';
        }

        return '/'.implode('/', array_map($this->encodeSegment(...), $segments));
    }

    private function encodeSegment(string $segment): string
    {
        if ($segment === '*') {
            return '*';
        }

        return str_replace('%2A', '*', rawurlencode($segment));
    }
}
