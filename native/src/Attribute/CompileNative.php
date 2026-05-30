<?php

namespace Native\Attribute;

use Attribute;

#[Attribute(
    Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION
)]
final readonly class CompileNative
{
}