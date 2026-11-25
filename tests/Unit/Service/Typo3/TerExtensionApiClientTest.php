<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Typo3;

use App\Service\Typo3\TerExtensionApiClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class TerExtensionApiClientTest extends TestCase
{
    /**
     * Live TER data from 2026-11-22.
     *
     * @param list<int> $typo3Versions
     */
    #[DataProvider('versions')]
    public function testTypo3VersionsComeFromTheDependency(array $typo3Versions, ?string $dependency, ?string $expected): void
    {
        $version = ['number' => '1.0.0', 'description' => '', 'typo3_versions' => $typo3Versions];
        if (null !== $dependency) {
            $version['dependencies'] = ['typo3' => $dependency];
        }
        $client = new TerExtensionApiClient(new MockHttpClient(new MockResponse(json_encode([[$version]], \JSON_THROW_ON_ERROR))));

        self::assertSame($expected, $client->getVersionHistory('ext')[0]['typo3Versions']);
    }

    /**
     * @return iterable<string, array{list<int>, ?string, ?string}>
     */
    public static function versions(): iterable
    {
        yield 'embedassets 1.4.0: highest major missing' => [[11, 12, 13], '>=11.5.0 <=14.99.99', '11, 12, 13, 14'];
        yield 'form_crshield 4.0.2: empty list' => [[], '>=14.3.0 <=14.3.99', '14'];
        yield 'bootstrap_package_iconpack 1.0.4' => [[10, 11, 12, 13], '>=10.4.0 <=14.9.99', '10, 11, 12, 13, 14'];
        yield 'filefill 5.0.0: list already right' => [[13], '>=13.4.0 <=13.4.99', '13'];
        yield 'no dependency: falls back to the list' => [[12, 13], null, '12, 13'];
        yield 'unreadable dependency: falls back to the list' => [[12], '*', '12'];
    }
}
