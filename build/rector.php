<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector;
use Rector\Config\RectorConfig;
use Rector\Symfony\Set\SymfonySetList;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/../config',
        __DIR__ . '/../src',
        __DIR__ . '/../tests/Functional',
        __DIR__ . '/../tests/Unit',
        __DIR__ . '/../tests/bootstrap.php',
    ])
    ->withSkip([
        // Rewrites static fn(string $a, string $b): int => version_compare($a, $b) to
        // version_compare(...), whose first-class-callable type includes version_compare's
        // optional 3rd $operator param - PHPStan then rejects it against usort()'s stricter
        // callable(string, string): int shape. Keep the explicit arrow function here.
        ArrowFunctionDelegatingCallToFirstClassCallableRector::class => [
            __DIR__ . '/../src/Service/Wordpress/PluginSynchronizer.php',
        ],
    ])
    ->withPhpSets()
    // uncomment to reach your current PHP version
    ->withPhpVersion(PhpVersion::PHP_84)
    ->withPreparedSets(symfonyCodeQuality: true)
    ->withComposerBased(symfony: true)
    ->withSymfonyContainerXml(__DIR__ . '/../var/cache/test/App_KernelTestDebugContainer.xml')
    // To have a better analysis from PHPStan, we teach it here some more things
    //->withPHPStanConfigs([])
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
    ])
;
