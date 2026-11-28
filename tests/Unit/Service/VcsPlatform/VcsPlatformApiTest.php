<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\VcsPlatform;

use App\Service\VcsPlatform\Codeberg;
use App\Service\VcsPlatform\GitHub;
use App\Service\VcsPlatform\GitLab;
use App\Service\VcsPlatform\RateLimitException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

class VcsPlatformApiTest extends TestCase
{
    /** @var list<array{url: string, headers: list<string>}> */
    private array $requests = [];

    public function testGitHubComparesTwoCommitsWithTheToken(): void
    {
        $gitHub = new GitHub($this->http(new JsonMockResponse(['commits' => [
            ['sha' => '3668a8c0', 'html_url' => 'https://github.com/b13/http2/commit/3668a8c0', 'commit' => ['message' => "!!![TASK] change requirements\n\nTYPO3 14 only"]],
        ]]), 'https://api.github.com/'), 'https://observer.example.com', 'secret-token');

        self::assertSame([['sha' => '3668a8c0', 'subject' => '!!![TASK] change requirements', 'message' => "!!![TASK] change requirements\n\nTYPO3 14 only", 'url' => 'https://github.com/b13/http2/commit/3668a8c0']], $gitHub->commitsBetween('b13/http2', '84692787', '78be3c0d'));
        self::assertSame('https://api.github.com/repos/b13/http2/compare/84692787...78be3c0d', $this->requests[0]['url']);
        self::assertContains('Authorization: Bearer secret-token', $this->requests[0]['headers']);
    }

    public function testCodebergComparesTheSameWayWithoutAToken(): void
    {
        $codeberg = new Codeberg($this->http(new JsonMockResponse(['total_commits' => 1, 'commits' => [
            ['sha' => 'e4c0f1fa', 'html_url' => 'https://codeberg.org/owner/extension/commit/e4c0f1fa', 'commit' => ['message' => '[!!!][TASK] Drop TYPO3 12']],
        ], 'files' => []]), 'https://codeberg.org/api/v1/'), 'https://observer.example.com');

        self::assertSame('[!!!][TASK] Drop TYPO3 12', $codeberg->commitsBetween('owner/extension', 'aaa', 'bbb')[0]['subject'] ?? null);
        self::assertSame('https://codeberg.org/api/v1/repos/owner/extension/compare/aaa...bbb', $this->requests[0]['url']);
        self::assertEmpty(array_filter($this->requests[0]['headers'], static fn (string $header): bool => str_starts_with($header, 'Authorization')));
    }

    public function testGitLabListsTheCommitsOfARangeInASubgroup(): void
    {
        $gitLab = new GitLab($this->http(new JsonMockResponse([
            ['id' => '87e8c32b', 'title' => 'feat: new cache layer', 'message' => "feat: new cache layer\n\nBREAKING CHANGE: the cache table is renamed", 'web_url' => 'https://gitlab.com/agency/typo3/extension/-/commit/87e8c32b'],
        ]), 'https://gitlab.com/api/v4/'), 'https://observer.example.com');

        self::assertSame("feat: new cache layer\n\nBREAKING CHANGE: the cache table is renamed", $gitLab->commitsBetween('agency/typo3/extension', 'aaa', 'bbb')[0]['message'] ?? null);
        self::assertSame('https://gitlab.com/api/v4/projects/agency%2Ftypo3%2Fextension/repository/commits?ref_name=aaa..bbb&per_page=100', $this->requests[0]['url']);
    }

    public function testGitLabsEmptyListForAnUnknownCommitIsUnreadable(): void
    {
        $gitLab = new GitLab($this->http(new JsonMockResponse([]), 'https://gitlab.com/api/v4/'), 'https://observer.example.com');

        self::assertNull($gitLab->commitsBetween('vendor/extension', 'deadbeef', 'bbb'), 'two versions are at least one commit apart');
    }

    public function testWhatAHostCantCompareIsUnreadableNotEmpty(): void
    {
        foreach ([404, 422, 403] as $status) {
            $gitHub = new GitHub(new MockHttpClient(new MockResponse('', ['http_code' => $status])), 'https://observer.example.com', 'secret-token');

            self::assertNull($gitHub->commitsBetween('vendor/gone', 'a', 'b'), "HTTP {$status}, e.g. a 403 for a blocked repository");
        }
    }

    public function testARateLimitStopsTheCaller(): void
    {
        foreach ([[403, ['x-ratelimit-remaining' => '0']], [429, ['retry-after' => '60']], [429, ['ratelimit-remaining' => '0']]] as [$status, $headers]) {
            $gitLab = new GitLab(new MockHttpClient(new MockResponse('', ['http_code' => $status, 'response_headers' => $headers])), 'https://observer.example.com');
            try {
                $gitLab->commitsBetween('vendor/extension', 'a', 'b');
                self::fail("HTTP {$status} should stop the caller");
            } catch (RateLimitException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function http(MockResponse $response, string $baseUri): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
            $this->requests[] = ['url' => $url, 'headers' => $options['headers']];

            return $response;
        }, $baseUri);
    }
}
