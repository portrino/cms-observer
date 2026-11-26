<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Craft;

use App\Service\Craft\PackagistApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class PackagistApiClientTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../../Fixtures/Packagist/';

    /** @var list<array{url: string, headers: list<string>}> */
    private array $requests = [];

    public function testExpandsTheMinifiedVersionsAndKeepsStableOnes(): void
    {
        $versions = $this->client(new MockResponse((string) file_get_contents(self::FIXTURES.'craft-seomatic.json')))->versions('nystudio107/craft-seomatic');

        self::assertNotNull($versions);
        $byVersion = array_column($versions, null, 'version');
        self::assertSame('5.1.24', $versions[0]['version']);
        self::assertSame('^5.0.0', $byVersion['5.1.24']['craftConstraint']);
        // 5.1.23 lists no "require" of its own: it inherits the one of 5.1.24
        self::assertSame('^5.0.0', $byVersion['5.1.23']['craftConstraint']);
        self::assertSame('^4.0.0', $byVersion['4.1.23']['craftConstraint']);
        self::assertArrayNotHasKey('5.0.0-beta.8', $byVersion);
        self::assertInstanceOf(\DateTimeImmutable::class, $byVersion['5.1.24']['releaseDate']);
        self::assertSame('https://repo.packagist.org/p2/nystudio107/craft-seomatic.json', $this->requests[0]['url']);
    }

    public function testAnUnknownPackageHasNoVersions(): void
    {
        self::assertNull($this->client(new MockResponse('', ['http_code' => 404]))->versions('no-such/package'));
    }

    public function testFetchesTheAdvisoriesOfSeveralPackagesAtOnce(): void
    {
        $advisories = $this->client(new MockResponse((string) file_get_contents(self::FIXTURES.'security-advisories.json')))
            ->advisories(['nystudio107/craft-seomatic', 'craftcms/cms', 'craftcms/cms']);

        self::assertCount(7, $advisories['nystudio107/craft-seomatic']);
        self::assertSame('<3.4.11', $advisories['nystudio107/craft-seomatic'][0]['affectedVersions']);
        self::assertCount(1, $this->requests);
        self::assertStringEndsWith('?packages[0]=nystudio107/craft-seomatic&packages[1]=craftcms/cms', $this->requests[0]['url']);
    }

    public function testNamesTheObserverAndItsContactInTheUserAgent(): void
    {
        $this->client(new MockResponse('{"advisories":[]}'))->advisories(['craftcms/cms']);

        self::assertContains('User-Agent: CMS Observer (+https://observer.example.com; mailto:ops@example.com)', $this->requests[0]['headers']);
    }

    private function client(MockResponse $response): PackagistApiClient
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
            $this->requests[] = ['url' => $url, 'headers' => $options['headers']];

            return $response;
        });

        return new PackagistApiClient($http, 'https://observer.example.com', 'ops@example.com');
    }
}
