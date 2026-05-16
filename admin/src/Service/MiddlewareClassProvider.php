<?php

namespace Symfonicat\Service;

use Psr\Http\Server\MiddlewareInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class MiddlewareClassProvider
{
    /**
     * @param iterable<MiddlewareInterface> $middlewares
     */
    public function __construct(
        #[AutowireIterator('kafkiansky.symfony.middleware')]
        private readonly iterable $middlewares,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function choices(): array
    {
        $choices = [];

        foreach ($this->classes() as $class) {
            $shortName = (new \ReflectionClass($class))->getShortName();
            $choices[$shortName] = $class;
        }

        ksort($choices, SORT_STRING);

        return $choices;
    }

    /**
     * @return list<class-string<MiddlewareInterface>>
     */
    public function classes(): array
    {
        $classes = [];

        foreach ($this->middlewares as $middleware) {
            if (!$middleware instanceof MiddlewareInterface) {
                continue;
            }

            $classes[] = $middleware::class;
        }

        $classes = array_values(array_unique($classes));
        sort($classes, SORT_STRING);

        return $classes;
    }
}
