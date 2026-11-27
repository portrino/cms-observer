<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\GitHubApiClient;
use App\Service\GitHubRateLimitException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

class GitHubApiClientTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function urls(): iterable
    {
        yield 'https with .git' => ['https://github.com/b13/http2.git', 'b13/http2'];
        yield 'https without' => ['https://github.com/georgringer/news', 'georgringer/news'];
        yield 'ssh' => ['git@github.com:portrino/px_dbsequencer.git', 'portrino/px_dbsequencer'];
        yield 'another host' => ['https://gitlab.com/vendor/package.git', null];
        yield 'a host that only starts like GitHub' => ['https://github.com.example.test/vendor/package.git', null];
        yield 'none' => [null, null];
    }

    #[DataProvider('urls')]
    public function testFindsTheRepositoryOnGitHubOnly(?string $url, ?string $repository): void
    {
        self::assertSame($repository, GitHubApiClient::repositoryOf($url));
    }

    public function testReadsTheCommitsBetweenTwoVersionsWithTheToken(): void
    {
        $requests = [];
        $client = new GitHubApiClient(new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): JsonMockResponse {
            $requests[] = ['url' => $url, 'headers' => $options['headers']];

            return new JsonMockResponse(['commits' => [
                ['sha' => '3668a8c0', 'html_url' => 'https://github.com/b13/http2/commit/3668a8c0', 'commit' => ['message' => "!!![TASK] change requirements\n\nTYPO3 14 only"]],
                ['sha' => '78be3c0d', 'commit' => ['message' => '[RELEASE] 4.0.0']],
            ]]);
        }, 'https://api.github.com/'), 'https://observer.example.com', 'secret-token');

        self::assertTrue($client->isConfigured());
        self::assertSame([
            ['sha' => '3668a8c0', 'subject' => '!!![TASK] change requirements', 'url' => 'https://github.com/b13/http2/commit/3668a8c0'],
            ['sha' => '78be3c0d', 'subject' => '[RELEASE] 4.0.0', 'url' => 'https://github.com/b13/http2/commit/78be3c0d'],
        ], $client->commitsBetween('b13/http2', '84692787', '78be3c0d'));
        self::assertStringStartsWith('https://api.github.com/repos/b13/http2/compare/84692787...78be3c0d', $requests[0]['url']);
        self::assertContains('Authorization: Bearer secret-token', $requests[0]['headers']);
    }

    public function testAnUnknownRepositoryHasNoCommits(): void
    {
        $client = new GitHubApiClient(new MockHttpClient(new MockResponse('', ['http_code' => 404])), 'https://observer.example.com', 'secret-token');

        self::assertSame([], $client->commitsBetween('vendor/gone', 'a', 'b'));
    }

    public function testARateLimitStopsTheCaller(): void
    {
        $client = new GitHubApiClient(new MockHttpClient(new MockResponse('', ['http_code' => 403])), 'https://observer.example.com', 'secret-token');

        $this->expectException(GitHubRateLimitException::class);
        $client->commitsBetween('b13/http2', 'a', 'b');
    }

    public function testWithoutATokenItIsOff(): void
    {
        self::assertFalse(new GitHubApiClient(new MockHttpClient(), 'https://observer.example.com', ' ')->isConfigured());
    }
}
