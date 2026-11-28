<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utility\Typo3;

use App\Utility\Typo3\ExtensionUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExtensionUtilityTest extends TestCase
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
        self::assertSame($expected, ExtensionUtility::supportedCoreMajors($require, [8, 12, 13, 14]));
    }

    #[DataProvider('matchCases')]
    public function testMatches(string $affectedVersionsRaw, string $version, ?bool $expected): void
    {
        self::assertSame($expected, ExtensionUtility::matchesAffectedVersions($affectedVersionsRaw, $version));
    }

    /**
     * @return iterable<string, array{string, string, ?bool}>
     */
    public static function matchCases(): iterable
    {
        yield 'exact match' => ['12.4.9', '12.4.9', true];
        yield 'exact non-match' => ['12.4.9', '12.4.10', false];

        yield '"and below" - at the bound' => ['10.9.2 and below', '10.9.2', true];
        yield '"and below" - under the bound' => ['10.9.2 and below', '10.9.1', true];
        yield '"and below" - over the bound' => ['10.9.2 and below', '10.9.3', false];

        yield '"and above" - at the bound' => ['11.0.0 and above', '11.0.0', true];
        yield '"and above" - over the bound' => ['11.0.0 and above', '12.0.0', true];
        yield '"and above" - under the bound' => ['11.0.0 and above', '10.9.9', false];

        yield 'inclusive range - lower bound' => ['11.0.0 - 12.6.0', '11.0.0', true];
        yield 'inclusive range - upper bound' => ['11.0.0 - 12.6.0', '12.6.0', true];
        yield 'inclusive range - inside' => ['11.0.0 - 12.6.0', '11.5.0', true];
        yield 'inclusive range - below' => ['11.0.0 - 12.6.0', '10.9.9', false];
        yield 'inclusive range - above' => ['11.0.0 - 12.6.0', '12.6.1', false];

        yield 'multiple segments - matches the second' => ['10.9.2 and below, 13.0.0 - 13.2.0', '13.1.0', true];
        yield 'multiple segments - matches neither' => ['10.9.2 and below, 13.0.0 - 13.2.0', '11.5.0', false];

        yield 'unrecognized segment alone is undetermined' => ['some free text', '1.0.0', null];
        yield 'unrecognized segment mixed with a non-matching one is undetermined' => ['10.9.2 and below, some free text', '11.0.0', null];
        yield 'unrecognized segment mixed with a matching one still matches' => ['some free text, 10.9.2 and below', '10.9.2', true];

        yield 'empty string is undetermined' => ['', '1.0.0', null];
        yield 'blank/whitespace-only segments are ignored, not treated as unrecognized' => [' , 10.9.2 and below , ', '10.9.2', true];
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function commitSubjects(): iterable
    {
        yield 'TYPO3 marker' => ['!!![TASK] change requirements', true];
        yield 'TYPO3 marker in brackets' => ['[!!!][TASK] Remove deprecated hooks', true];
        yield 'Conventional Commits' => ['feat(api)!: drop the old endpoint', true];
        yield 'Conventional Commits footer' => ['BREAKING CHANGE: the config moved', true];
        yield 'not breaking' => ['[TASK] Add non-breaking type annotations', false];
        yield 'a breaking change of the core' => ['[TASK] Respect breaking change in PluginPreviewRenderer', false];
        yield 'plain commit' => ['[BUGFIX] rm foo header', false];
    }

    #[DataProvider('commitSubjects')]
    public function testOnlyMarkedCommitsCountAsBreaking(string $subject, bool $breaking): void
    {
        self::assertSame($breaking, ExtensionUtility::marksBreakingCommit($subject));
    }

    public function testFindsTheConventionalCommitsFooterOnALineOfItsOwn(): void
    {
        self::assertTrue(ExtensionUtility::hasBreakingFooter("feat: new cache layer\n\nBREAKING CHANGE: the cache table is renamed"));
        self::assertTrue(ExtensionUtility::hasBreakingFooter("refactor: drop PHP 8.1\n\nBREAKING-CHANGE: needs PHP 8.2"));
        self::assertFalse(ExtensionUtility::hasBreakingFooter("docs: explain the setup\n\nMentions a BREAKING CHANGE: of the core in passing"));
        self::assertFalse(ExtensionUtility::hasBreakingFooter('[BUGFIX] rm foo header'));
    }
}
