<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\CmsType;
use PHPUnit\Framework\TestCase;

class CmsTypeTest extends TestCase
{
    public function testLinksTheReleaseNotesOfAVersion(): void
    {
        self::assertSame('https://get.typo3.org/release-notes/13.4.21', CmsType::Typo3->releaseNotesUrl('13.4.21'));
        self::assertSame('https://wordpress.org/documentation/wordpress-version/version-6-8-1/', CmsType::WordPress->releaseNotesUrl('6.8.1'));
    }

    public function testEveryCmsHasAnExistingIcon(): void
    {
        foreach (CmsType::cases() as $type) {
            self::assertFileExists(dirname(__DIR__, 3).'/assets/'.$type->icon(), $type->label());
        }
    }
}
