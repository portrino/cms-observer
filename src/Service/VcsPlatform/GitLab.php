<?php

declare(strict_types=1);

namespace App\Service\VcsPlatform;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * gitlab.com, public repositories without a token (500 requests a minute). Its commit list
 * takes a range, unlike its "compare", which sends every diff along.
 *
 * shortcut: the first 100 commits of a range only, plenty between two releases; page through
 * "x-next-page" if a package releases more than that at once.
 */
class GitLab extends AbstractVcsPlatform
{
    public function __construct(
        private readonly HttpClientInterface $gitlabClient,
        #[Autowire(env: 'DEFAULT_URI')]
        string $observerUrl,
    ) {
        // repositories may sit in nested groups, e.g. "group/subgroup/project"
        parent::__construct('gitlab.com', true, $observerUrl);
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function commitsBetween(string $repository, string $base, string $head): ?array
    {
        $response = $this->gitlabClient->request('GET', sprintf('projects/%s/repository/commits', rawurlencode($repository)), [
            'headers' => ['User-Agent' => $this->userAgent()],
            'query' => ['ref_name' => $base.'..'.$head, 'per_page' => 100],
        ]);
        if (!$this->isReadable($response, $repository)) {
            return null;
        }

        $commits = [];
        foreach ($response->toArray() as $commit) {
            if (is_array($commit) && is_string($commit['id'] ?? null) && is_string($commit['message'] ?? null)) {
                $commits[] = self::commit($commit['id'], $commit['message'], is_string($commit['web_url'] ?? null) ? $commit['web_url'] : '');
            }
        }

        // GitLab answers an unknown commit with an empty list
        return self::nonEmpty($commits);
    }
}
