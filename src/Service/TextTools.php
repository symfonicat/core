<?php

namespace App\Service;

use Symfonicat\Attribute\CompileNative;

final class TextTools implements TextToolsInterface
{
    #[CompileNative]
    public function removeString(
        string $value,
        string $needle,
    ): string {
        return str_replace(
            $needle,
            '',
            $value
        );
    }
}
