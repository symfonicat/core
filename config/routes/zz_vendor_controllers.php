<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Yaml\Yaml;

return static function (RoutingConfigurator $routes): void {
    $configuredVendors = static function (string $configPath): array {
        $config = is_file($configPath) ? Yaml::parseFile($configPath) : [];
        $vendors = $config['symfonicat']['vendors'] ?? ['symfonicat'];

        if (!is_array($vendors)) {
            return ['symfonicat'];
        }

        $vendors = array_values(array_unique(array_filter(array_map(
            static fn (mixed $vendor): string => trim((string) $vendor, " \t\n\r\0\x0B/"),
            $vendors,
        ))));

        return $vendors === [] ? ['symfonicat'] : $vendors;
    };

    foreach ($configuredVendors(\dirname(__DIR__).'/packages/symfonicat.yaml') as $vendor) {
        $routes->import('../../vendor/'.$vendor.'/*/src/Controller/*.php', 'attribute', true);
    }
};
