<?php

namespace Symfonicat\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ActiveExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('active', $this->render(...)),
        ];
    }

    public function render(string|array $routeNames, string $value): string
    {
        $currentRoute = $this->requestStack->getCurrentRequest()?->attributes->get('_route');
        if (!\is_string($currentRoute)) {
            return '';
        }

        if (\is_string($routeNames)) {
            return $this->matchesPattern($currentRoute, $routeNames) ? $value : '';
        }

        foreach ($routeNames as $routeName) {
            if (\is_string($routeName) && $this->matchesPattern($currentRoute, $routeName)) {
                return $value;
            }
        }

        return '';
    }

    private function matchesPattern(string $currentRoute, string $pattern): bool
    {
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';

        return 1 === preg_match($regex, $currentRoute);
    }
}
