<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Typo3;

use App\Service\Typo3\CoreRequirement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CoreRequirementTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, string>, list<int>|null}>
     */
    public static function requirements(): iterable
    {
        yield 'typo3/cms-core' => [['typo3/cms-core' => '^14.3'], [14]];
        yield 'only another core package, as before TYPO3 14' => [['typo3/cms-frontend' => '^12.4 || ^13.4'], [12, 13]];
        yield 'every core package must admit the major' => [['typo3/cms-extbase' => '^12.4 || ^13.4', 'typo3/cms-frontend' => '^13.4 || ^14.0'], [13]];
        yield 'the old core package' => [['typo3/cms' => '^8.7'], [8]];
        yield 'installers have versions of their own' => [['typo3/cms-core' => '^13.4', 'typo3/cms-composer-installers' => '^5.0'], [13]];
        yield 'other packages don\'t count' => [['php' => '^8.2', 'typo3/cms-core' => '^12.4 || ^13.4', 'symfony/console' => '^7.0'], [12, 13]];
        yield 'no core package' => [['php' => '^8.2', 'typo3/cms-composer-installers' => '^5.0'], null];
        yield 'none of the known majors' => [['typo3/cms-core' => '^11.5'], []];
    }

    /**
     * @param array<string, string> $require
     * @param list<int>|null        $expected
     */
    #[DataProvider('requirements')]
    public function testFindsTheMajorsEveryCorePackageAdmits(array $require, ?array $expected): void
    {
        self::assertSame($expected, CoreRequirement::supportedMajors($require, [8, 12, 13, 14]));
    }
}
