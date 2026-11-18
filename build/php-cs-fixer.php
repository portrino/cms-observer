<?php

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config->getFinder()
    ->exclude(['node_modules', 'var'])
    ->in([
        __DIR__ . '/../config',
        __DIR__ . '/../src',
        __DIR__ . '/../tests/Functional',
        __DIR__ . '/../tests/Unit',
    ])
    ->notPath([
        __DIR__ . '/../config/bundles.php',
        __DIR__ . '/../config/reference.php',
    ])
    ->append([__DIR__ . '/../tests/bootstrap.php']);
$config->setRules([
    '@Symfony' => true,
]);
return $config;
