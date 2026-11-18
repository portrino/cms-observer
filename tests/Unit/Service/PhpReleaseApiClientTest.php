<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\PhpReleaseApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class PhpReleaseApiClientTest extends TestCase
{
    public function testReturnsNewestSecurityTaggedRelease(): void
    {
        // shape as returned by https://www.php.net/releases/index.php?json&version=8.4
        $client = new PhpReleaseApiClient(new MockHttpClient(new JsonMockResponse([
            '8.4.26' => ['date' => '24 Sep 2026', 'tags' => ['security']],
            '8.4.28' => ['date' => '20 Nov 2026', 'tags' => ['']],
            '8.4.27' => ['date' => '21 Oct 2026', 'tags' => ['security']],
            '8.4.25' => ['date' => '27 Aug 2026', 'tags' => ['']],
        ])));

        self::assertSame('8.4.27', $client->fetchLatestSecurityVersion('8.4'));
    }

    public function testReturnsNullWithoutAnySecurityRelease(): void
    {
        $client = new PhpReleaseApiClient(new MockHttpClient(new JsonMockResponse([
            '8.5.0' => ['date' => '20 Nov 2025', 'tags' => []],
        ])));

        self::assertNull($client->fetchLatestSecurityVersion('8.5'));
    }
}
