<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Exception\Configuration\InvalidConfigurationException;

try {
    return RectorConfig::configure()
        ->withPaths([
            __DIR__ . '/DataTable',
            __DIR__ . '/test',
        ])
        // uncomment to reach your current PHP version
        ->withPhpSets(php83: true)
        ->withAttributesSets(phpunit: true)
        ->withTypeCoverageLevel(6)
        ->withDeadCodeLevel(6)
        ->withCodeQualityLevel(6);
} catch (InvalidConfigurationException $e) {
    print $e->getMessage();
}
