<?php

namespace Native\DependencyInjection\Compiler;

use Native\Attribute\CompileNative;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class NativeProxyCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $serviceId => $definition) {
            $class = $definition->getClass();

            if ($class === null || !class_exists($class)) {
                continue;
            }

            $reflectionClass = new \ReflectionClass($class);

            if (!$this->hasCompileNativeMethod($reflectionClass)) {
                continue;
            }

            $proxyClass = $this->getProxyClass($class);

            if (!class_exists($proxyClass)) {
                continue;
            }

            $proxyId = $serviceId . '.native_proxy';

            $container
                ->register(
                    $proxyId,
                    $proxyClass,
                )
                ->setArguments([
                    new Reference($serviceId),
                ])
                ->setAutowired(
                    true,
                )
                ->setAutoconfigured(
                    true,
                );

            foreach (class_implements($class) ?: [] as $interface) {
                if (
                    !(
                        str_starts_with($interface, 'App\\')
                        || str_starts_with($interface, 'Native\\')
                        || str_starts_with($interface, 'Symfonicat\\')
                    )
                ) {
                    continue;
                }

                $container
                    ->setAlias($interface, $proxyId)
                    ->setPublic(false);
            }
        }
    }

    private function hasCompileNativeMethod(\ReflectionClass $reflectionClass): bool
    {
        foreach ($reflectionClass->getMethods() as $method) {
            if ($method->getAttributes(CompileNative::class) !== []) {
                return true;
            }
        }

        return false;
    }

    private function getProxyClass(string $class): string
    {
        return str_replace(
            'App\\Service\\',
            'Native\\NativeProxy\\Service\\',
            $class,
        ) . 'NativeProxy';
    }
}
