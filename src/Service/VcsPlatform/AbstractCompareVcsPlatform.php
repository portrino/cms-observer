<?php

declare(strict_types=1);

namespace App\Service\VcsPlatform;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * GitHub and Forgejo (Codeberg) compare two commits the same way:
 * "repos/{owner}/{repo}/compare/{base}...{head}" with commits[].sha, .html_url, .commit.message.
 *
 * shortcut: the first page only, up to 250 commits on GitHub, which is plenty between two
 * releases; page through "Link" headers if a package releases more than that at once.
 */
abstract class AbstractCompareVcsPlatform extends AbstractVcsPlatform
{
    public function __construct(
        private readonly HttpClientInterface $client,
        string $host,
        string $observerUrl,
    ) {
        parent::__construct($host, observerUrl: $observerUrl);
    }

    public function commitsBetween(string $repository, string $base, string $head): ?array
    {
        $response = $this->client->request('GET', sprintf('repos/%s/compare/%s...%s', $repository, $base, $head), [
            'headers' => ['User-Agent' => $this->userAgent()] + $this->authorization(),
        ]);
        if (!$this->isReadable($response, $repository)) {
            return null;
        }

        $commits = [];
        foreach ($response->toArray()['commits'] ?? [] as $commit) {
            if (is_array($commit) && is_string($commit['sha'] ?? null) && is_string($commit['commit']['message'] ?? null)) {
                $commits[] = self::commit($commit['sha'], $commit['commit']['message'], is_string($commit['html_url'] ?? null) ? $commit['html_url'] : '');
            }
        }

        return self::nonEmpty($commits);
    }

    /**
     * @return array<string, string>
     */
    protected function authorization(): array
    {
        return [];
    }
}
