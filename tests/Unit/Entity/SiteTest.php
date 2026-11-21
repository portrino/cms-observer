<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\CmsType;
use App\Entity\Site;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SiteTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, bool}>
     */
    public static function applicationContexts(): iterable
    {
        yield 'TYPO3 Production' => ['Production', false];
        yield 'TYPO3 Production sub-context' => ['Production/Live', false];
        yield 'TYPO3 Development' => ['Development', true];
        yield 'TYPO3 Testing' => ['Testing', true];
        yield 'WordPress production' => ['production', false];
        yield 'WordPress staging' => ['staging', true];
        yield 'WordPress development' => ['development', true];
        yield 'not reported' => [null, false];
    }

    #[DataProvider('applicationContexts')]
    public function testRunsOutsideProductionContext(?string $context, bool $expected): void
    {
        $site = new Site('Example', CmsType::Typo3, 'https://example.test', 'token-hash');
        $site->recordApplicationContext($context);

        self::assertSame($expected, $site->runsOutsideProductionContext());
    }
}
