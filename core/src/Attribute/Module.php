<?php

namespace Symfonicat\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Module
{
    public function __construct(
        public string $permission = 'PUBLIC_ACCESS',
    ) {
    }
}
