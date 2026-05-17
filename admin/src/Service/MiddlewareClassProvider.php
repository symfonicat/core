<?php

namespace Symfonicat\Service;

use Psr\Http\Server\MiddlewareInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Finder\Finder;

final class MiddlewareClassProvider
{
    /**
     * @param iterable<MiddlewareInterface> $middlewares
     */
    public function __construct(
        #[AutowireIterator('kafkiansky.symfony.middleware')]
        private readonly iterable $middlewares,
        private readonly PackageDiscoveryService $packageDiscoveryService,
    ) {
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function choices(): array
    {
        $choices = [];

        foreach ($this->definitions() as $definition) {
            $choices[$definition['group']][$definition['label']] = $definition['class'];
        }

        ksort($choices, SORT_STRING);
        foreach ($choices as &$groupChoices) {
            ksort($groupChoices, SORT_STRING);
        }

        unset($groupChoices);

        return $choices;
    }

    /**
     * @return array<int, array{id: string, class: class-string<MiddlewareInterface>, group: string, label: string}>
     */
    public function definitions(): array
    {
        $definitions = [];

        foreach (array_merge(
            $this->registeredMiddlewareDefinitions(),
            $this->discoveredPackageMiddlewareDefinitions(),
        ) as $definition) {
            $definitions[$definition['class']] = $definition;
        }

        $definitions = array_values($definitions);
        usort($definitions, static fn (array $left, array $right): int => $left['id'] <=> $right['id']);

        return $definitions;
    }

    /**
     * @return array<int, class-string<MiddlewareInterface>>
     */
    public function classes(): array
    {
        return array_values(array_map(
            static fn (array $definition): string => $definition['class'],
            $this->definitions(),
        ));
    }

    /**
     * @return array<int, array{id: string, class: class-string<MiddlewareInterface>, group: string, label: string}>
     */
    private function registeredMiddlewareDefinitions(): array
    {
        $definitions = [];

        foreach ($this->middlewares as $middleware) {
            if (!$middleware instanceof MiddlewareInterface) {
                continue;
            }

            $class = $middleware::class;
            $label = (new \ReflectionClass($class))->getShortName();
            $definitions[] = [
                'id' => $this->middlewareId('core', $label),
                'class' => $class,
                'group' => 'core',
                'label' => $label,
            ];
        }

        return $definitions;
    }

    /**
     * @return array<int, array{id: string, class: class-string<MiddlewareInterface>, group: string, label: string}>
     */
    private function discoveredPackageMiddlewareDefinitions(): array
    {
        $definitions = [];

        foreach ($this->packageDiscoveryService->findSymfonicatPackages() as $package) {
            $middlewareDirectory = rtrim($package['installPath'], '/').'/src/Middleware';
            if (!is_dir($middlewareDirectory)) {
                continue;
            }

            $group = $package['vendor'] === 'core'
                ? 'core'
                : sprintf('%s/%s', $package['vendor'], $package['package']);

            $finder = new Finder();
            foreach ($finder->files()->in($middlewareDirectory)->name('*.php') as $file) {
                $path = $file->getRealPath() ?: $file->getPathname();
                $class = $this->classFromPackageFile($package['installPath'], $path);
                if ($class === null || !class_exists($class) || !is_a($class, MiddlewareInterface::class, true)) {
                    continue;
                }

                $label = (new \ReflectionClass($class))->getShortName();
                $definitions[] = [
                    'id' => $this->middlewareId($group, $label),
                    'class' => $class,
                    'group' => $group,
                    'label' => $label,
                ];
            }
        }

        return $definitions;
    }

    private function middlewareId(string $group, string $label): string
    {
        $group = trim($group, " \t\n\r\0\x0B/");
        $label = trim($label, " \t\n\r\0\x0B/");

        if ($group === '' || $label === '') {
            throw new \InvalidArgumentException('Middleware id components must be non-empty.');
        }

        return $group.'/'.$label;
    }

    private function classFromPackageFile(string $installPath, string $path): ?string
    {
        $installPath = rtrim(str_replace('\\', '/', $installPath), '/').'/';
        $path = str_replace('\\', '/', $path);

        if (!str_starts_with($path, $installPath.'src/')) {
            return null;
        }

        $relative = substr($path, strlen($installPath.'src/'));
        $relative = preg_replace('/\\.php$/', '', $relative);
        if (!is_string($relative) || $relative === '') {
            return null;
        }

        return 'Symfonicat\\'.str_replace('/', '\\', $relative);
    }
}
