<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Exception\Configuration\InvalidConfigurationException;
use Rector\Privatization\Rector\Class_\FinalizeTestCaseClassRector;

try {
    return RectorConfig::configure()
        ->withPaths([
            __DIR__ . '/DataTable',
            __DIR__ . '/test',
        ])
        // uncomment to reach your current PHP version
        ->withPhpSets(php83: true)
        ->withAttributesSets(phpunit: true)
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
            typeDeclarations: true,
            phpunitCodeQuality: true
        )
        ->withSkip([
                FinalizeTestCaseClassRector::class => [
                    __DIR__ . '/test/MySqlDataTableTest.php' // need it to be extendable for MySqlDataTableWithAutoIncTest
                ]
            ]);
} catch (InvalidConfigurationException $e) {
    print $e->getMessage();
}
