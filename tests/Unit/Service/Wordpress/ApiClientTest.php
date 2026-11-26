<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Wordpress;

use App\Service\Wordpress\ApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ApiClientTest extends TestCase
{
    public function testReadsTheReleaseDatePerTagAndLogsAnUnreadableOne(): void
    {
        $logger = new BufferingLogger();
        $client = new ApiClient(new MockHttpClient(new MockResponse(<<<'XML'
            <?xml version="1.0" encoding="utf-8"?>
            <D:multistatus xmlns:D="DAV:">
              <D:response><D:href>/akismet/tags/</D:href><D:propstat><D:prop><D:getlastmodified>Mon, 01 Jan 2024 10:00:00 GMT</D:getlastmodified></D:prop></D:propstat></D:response>
              <D:response><D:href>/akismet/tags/5.3/</D:href><D:propstat><D:prop><D:getlastmodified>Tue, 02 Jan 2024 10:00:00 GMT</D:getlastmodified></D:prop></D:propstat></D:response>
              <D:response><D:href>/akismet/tags/5.4/</D:href><D:propstat><D:prop><D:getlastmodified>not a date</D:getlastmodified></D:prop></D:propstat></D:response>
            </D:multistatus>
            XML)), $logger);

        $dates = $client->getTagDates('akismet');

        self::assertSame(['5.3'], array_keys($dates));
        self::assertSame('2024-01-02', $dates['5.3']->format('Y-m-d'));
        $logs = $logger->cleanLogs();
        self::assertCount(1, $logs);
        self::assertSame('warning', $logs[0][0]);
        self::assertSame('5.4', $logs[0][2]['version']);
    }

    public function testLogsAnUnreadableTagListing(): void
    {
        $logger = new BufferingLogger();
        $client = new ApiClient(new MockHttpClient(new MockResponse('<html>maintenance</html')), $logger);

        self::assertSame([], $client->getCoreTagDates());
        $logs = $logger->cleanLogs();
        self::assertCount(1, $logs);
        self::assertSame('warning', $logs[0][0]);
        self::assertSame('https://core.svn.wordpress.org/tags/', $logs[0][2]['url']);
    }

    public function testLogsABlockedRequestButKeepsTheKnownData(): void
    {
        $logger = new BufferingLogger();
        $client = new ApiClient(new MockHttpClient([
            new MockResponse('{}', ['http_code' => 429]),
            new MockResponse('{}', ['http_code' => 403]),
            new MockResponse('{}', ['http_code' => 404]),
        ]), $logger);

        self::assertNull($client->getPluginInfo('akismet'));
        self::assertNull($client->getThemeInfo('astra'));
        self::assertNull($client->getPluginInfo('premium-plugin')); // the usual unknown slug, not logged
        $logs = $logger->cleanLogs();
        self::assertCount(2, $logs);
        self::assertSame(['error', 429, 'plugin', 'akismet'], [$logs[0][0], $logs[0][2]['status'], $logs[0][2]['kind'], $logs[0][2]['slug']]);
        self::assertSame(['error', 403, 'theme', 'astra'], [$logs[1][0], $logs[1][2]['status'], $logs[1][2]['kind'], $logs[1][2]['slug']]);
    }
}
