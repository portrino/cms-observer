<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The commits between two versions of a package on GitHub (ADR-0007). Only with a token:
 * without one GitHub allows 60 requests an hour. The token needs no permissions, the
 * repositories are public.
 */
class GitHubApiClient
{
    public function __construct(
        private readonly HttpClientInterface $githubClient,
        #[Autowire(env: 'DEFAULT_URI')]
        private readonly string $observerUrl,
        #[Autowire(env: 'GITHUB_API_TOKEN')]
        private readonly string $token,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->token);
    }

    /**
     * "b13/http2" from https://github.com/b13/http2.git or git@github.com:b13/http2.git, null for another host.
     */
    public static function repositoryOf(?string $url): ?string
    {
        return 1 === preg_match('~^(?:https?://(?:www\.)?github\.com/|git@github\.com:)([\w.-]+/[\w.-]+?)(?:\.git)?/?$~i', (string) $url, $match) ? $match[1] : null;
    }

    /**
     * The commits after $base up to $head, oldest first, with their first line.
     *
     * shortcut: GitHub lists up to 250 commits without paging, which is plenty between two
     * releases; page through "Link" headers if a package releases more than that at once.
     *
     * @return list<array{sha: string, subject: string, url: string}>|null null when GitHub can't
     *                                                                     compare them, e.g. a deleted repository or a moved tag
     *
     * @throws GitHubRateLimitException when GitHub declines further requests for now
     */
    public function commitsBetween(string $repository, string $base, string $head): ?array
    {
        $response = $this->githubClient->request('GET', sprintf('repos/%s/compare/%s...%s', $repository, $base, $head), [
            'headers' => [
                'User-Agent' => sprintf('CMS Observer (+%s)', $this->observerUrl),
                'Authorization' => 'Bearer '.trim($this->token),
            ],
        ]);
        $status = $response->getStatusCode();
        $headers = $response->getHeaders(false);
        if (in_array($status, [403, 429], true) && ('0' === ($headers['x-ratelimit-remaining'][0] ?? null) || isset($headers['retry-after']))) {
            throw new GitHubRateLimitException(sprintf('GitHub answered %d for %s', $status, $repository));
        }
        if (in_array($status, [403, 404, 422], true)) {
            return null; // this pair only: e.g. a deleted or blocked repository, an unknown commit
        }

        $commits = $response->toArray()['commits'] ?? [];

        return array_values(array_filter(array_map(
            static fn (mixed $commit): ?array => self::commitOf($commit, $repository),
            is_array($commits) ? $commits : [],
        )));
    }

    /**
     * @return array{sha: string, subject: string, url: string}|null null for an entry without a hash or message
     */
    private static function commitOf(mixed $commit, string $repository): ?array
    {
        if (!is_array($commit) || !is_string($commit['sha'] ?? null) || !is_string($commit['commit']['message'] ?? null)) {
            return null;
        }

        return [
            'sha' => $commit['sha'],
            'subject' => trim(explode("\n", $commit['commit']['message'])[0]),
            'url' => is_string($commit['html_url'] ?? null) ? $commit['html_url'] : 'https://github.com/'.$repository.'/commit/'.$commit['sha'],
        ];
    }
}
