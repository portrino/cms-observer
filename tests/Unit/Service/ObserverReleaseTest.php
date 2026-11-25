<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ObserverRelease;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class ObserverReleaseTest extends TestCase
{
    public function testHintsAtANewerReleaseOnlyAfterTheSync(): void
    {
        $cache = new ArrayAdapter();
        // trimmed shape of https://api.github.com/repos/portrino/cms-observer/releases/latest
        $http = new MockHttpClient(new JsonMockResponse(['tag_name' => '0.13.0', 'html_url' => 'https://github.com/portrino/cms-observer/releases/tag/0.13.0']));
        $release = new ObserverRelease($http, $cache, '0.12.3');

        self::assertNull($release->availableUpdate());
        self::assertSame('0.13.0', $release->sync());
        self::assertSame(['version' => '0.13.0', 'url' => 'https://github.com/portrino/cms-observer/releases/tag/0.13.0'], $release->availableUpdate());
    }

    #[DataProvider('upToDateVersions')]
    public function testGivesNoHintWhenUpToDateOrOnABranch(string $installed): void
    {
        $cache = new ArrayAdapter();
        new ObserverRelease(new MockHttpClient(new JsonMockResponse(['tag_name' => 'v0.13.0', 'html_url' => 'https://example.test'])), $cache)->sync();

        self::assertNull(new ObserverRelease(new MockHttpClient(), $cache, $installed)->availableUpdate());
    }

    /** @return iterable<string, array{string}> */
    public static function upToDateVersions(): iterable
    {
        yield 'same' => ['0.13.0'];
        yield 'newer' => ['0.14.0'];
        yield 'branch' => ['dev-main'];
    }
}
