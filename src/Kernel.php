<?php

namespace App;

use Symfonicat\DependencyInjection\Compiler\NativeProxyCompilerPass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
        require_once dirname(__DIR__).'/config/polyfill.php';

        parent::build($container);

        $container->addCompilerPass(
            new NativeProxyCompilerPass()
        );
    }
}
