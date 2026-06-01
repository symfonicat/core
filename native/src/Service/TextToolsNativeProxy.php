<?php

declare(strict_types=1);

namespace Symfonicat\NativeProxy\Service;

use App\Service\TextToolsInterface;

final readonly class TextToolsNativeProxy implements TextToolsInterface
{
    public function removeString(

        string $value,
        string $needle,

    ): string {

        return \native_remove_string(
            $value,
            $needle,
        );

    }
}
