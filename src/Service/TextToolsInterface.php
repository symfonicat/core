<?php

namespace App\Service;

interface TextToolsInterface
{
    public function removeString(
        string $value,
        string $needle,
    ): string;
}