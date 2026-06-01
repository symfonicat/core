<?php

namespace Symfonicat\Contract;

interface AdminYamlDumper
{
    /**
     * @return array<string, int>
     */
    public function dump(): array;
}
