<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utility\Craft;

use App\Entity\Craft\Advisory;
use App\Utility\Craft\AdvisoryUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AdvisoryUtilityTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function versions(): iterable
    {
        yield 'affected in the 4.x range' => ['4.18.1', 'PKSA-high'];
        yield 'affected by both, the more severe wins' => ['5.10.2', 'PKSA-critical'];
        yield 'fixed' => ['5.10.6', null];
        yield 'a branch is unknown, not insecure' => ['dev-main', null];
    }

    #[DataProvider('versions')]
    public function testFindsTheMostSevereAdvisoryOfAVersion(string $version, ?string $expected): void
    {
        $advisories = [
            new Advisory('PKSA-high', 'craftcms/cms', 'Stored XSS', 'https://example.test/1', '>=4.0.0-RC1,<4.18.2|>=5.0.0-RC1,<5.10.6', null, 'high'),
            new Advisory('PKSA-critical', 'craftcms/cms', 'RCE', 'https://example.test/2', '>=5.0.0-RC1,<5.10.5', 'CVE-2026-1', 'critical'),
        ];

        self::assertSame($expected, AdvisoryUtility::mostSevere($advisories, $version)?->getAdvisoryId());
    }
}
