<?php

namespace Symfonicat\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Module
{
    public array $methods;

    public function __construct(
        array $methods = ['POST'],
        public string $permission = 'PUBLIC_ACCESS',
    ) {
        $this->methods = $methods;
    }
}
