<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use DoctrineMigrations\Version20261123070037;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The migration recognizes every sentence the Observer wrote into the history before
 * translations, and the English translation reproduces it exactly.
 */
class SiteEventMessagesTest extends KernelTestCase
{
    /** @return iterable<array{string, string, non-empty-string}> */
    public static function texts(): iterable
    {
        $subjects = ['No reports', 'Extension news 11.4.0', 'Plugin akismet 5.3', 'Theme astra 4.1.0', 'PHP 8.2.27', 'Database 10.11.6', 'TYPO3 core 13.4.30', 'WordPress core 6.5.2', 'Application context Development/Docker'];
        foreach ($subjects as $text) {
            yield $text => ['SUBJECTS', $text, 'event.subject.'];
        }
        $details = [
            'No report since 2026-09-13 22:47, the security status is no longer up to date.',
            'Matches TYPO3-EXT-SA-2026-022 (Critical): https://typo3.org/security/advisory/typo3-ext-sa-2026-022',
            'Matches TYPO3-EXT-SA-2026-023 (unknown severity): https://typo3.org/a',
            'CVE-2024-1234 (high): https://www.wordfence.com/threat-intel/vulnerabilities/id/abc',
            'Some title (unknown severity): https://www.wordfence.com/x',
            'TER marked this exact version insecure (review_state).',
            'PHP version is now insecure (past end of life).',
            'PHP version is missing security fixes (fixed in 8.2.35).',
            'PHP version is secure again.',
            'Database version is now insecure (past end of life).',
            'Database version is secure again.',
            'The production site runs outside the Production context: error details and debug output can be public.',
            'The production site runs in the Production context again.',
            'Core version is now insecure (unmaintained or superseded by a security release).',
            'Core version is secure again.',
            '4 packages',
        ];
        foreach ($details as $text) {
            yield $text => ['DETAILS', $text, 'event.detail.'];
        }
    }

    /** @param non-empty-string $keyPrefix */
    #[DataProvider('texts')]
    public function testTheOldTextIsRecognizedAndReproduced(string $patterns, string $text, string $keyPrefix): void
    {
        $message = Version20261123070037::match(new \ReflectionClassConstant(Version20261123070037::class, $patterns)->getValue(), $text);

        self::assertNotNull($message);
        self::assertStringStartsWith($keyPrefix, $message['key']);
        $translator = static::getContainer()->get(TranslatorInterface::class);
        self::assertSame($text, $translator->trans($message['key'], $message['params'], null, 'en'));
        self::assertNotSame($message['key'], $translator->trans($message['key'], $message['params'], null, 'de'));
    }
}
