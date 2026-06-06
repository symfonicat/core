<?php

namespace Symfonicat\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class ModuleRoute
{
    public string $package;

    public function __construct(
        public ?string $path = null,
    )
    {
        $this->package = $this->packageName();

        $this->path ??= '/m/'.$this->package;
    }

    private function packageName(): string
    {
        $composerFile = dirname(__DIR__, 3).'/composer.json';
        if (!is_file($composerFile)) {
            return 'symfonicat/core';
        }

        $composer = json_decode((string) file_get_contents($composerFile), true);

        return is_array($composer) && isset($composer['name']) ? (string) $composer['name'] : 'symfonicat/core';
    }
}
