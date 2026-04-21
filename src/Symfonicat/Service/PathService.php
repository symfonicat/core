<?php

namespace Symfonicat\Service;

use Symfony\Component\HttpFoundation\RequestStack;

final class PathService
{
    public function __construct (

        private readonly RequestStack $requestStack

    ) {
        
    }

    public function arg (int $index) : string | NULL
    {
        $parts = $this->args();

        return $parts[$index] ?? NULL;
    }

    /**
     * @return list<string>
     */
    public function args(): array
    {
        $path = trim($this->requestStack->getCurrentRequest()?->getPathInfo() ?? '', '/');
        if ($path === '') {
            return [];
        }

        return array_values(array_filter(
            explode('/', $path),
            static fn (string $part): bool => $part !== '',
        ));
    }

    public function path(): string
    {
        $path = trim($this->requestStack->getCurrentRequest()?->getPathInfo() ?? '', '/');

        return $path === '' ? '/' : '/'.$path;
    }

    /**
     * @param list<string> $arguments
     */
    public function matchesArguments(array $arguments, ?string $path = null): bool
    {
        $pattern = $this->compileArgumentsRegex($arguments);
        if ($pattern === null) {
            return false;
        }

        $path ??= $this->path();

        return @preg_match($pattern, $path) === 1;
    }

    /**
     * @param list<string> $arguments
     */
    public function implodeArguments(array $arguments): string
    {
        $arguments = array_values(array_filter(
            array_map(
                static fn (mixed $argument): string => trim((string) $argument, " \t\n\r\0\x0B/"),
                $arguments,
            ),
            static fn (string $argument): bool => $argument !== '',
        ));

        return $arguments === [] ? '' : '/'.implode('/', $arguments);
    }

    /**
     * @param list<string> $arguments
     */
    private function compileArgumentsRegex(array $arguments): ?string
    {
        $arguments = array_values(array_filter(
            array_map(
                static fn (mixed $argument): string => trim((string) $argument, " \t\n\r\0\x0B/"),
                $arguments,
            ),
            static fn (string $argument): bool => $argument !== '',
        ));

        if ($arguments === []) {
            return null;
        }

        $segments = array_map(
            static fn (string $argument): string => $argument === '*' ? '[^/]*' : str_replace('~', '\\~', $argument),
            $arguments,
        );

        return '~^/'.implode('/', $segments).'$~u';
    }
}
