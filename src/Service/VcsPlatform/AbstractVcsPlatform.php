<?php

declare(strict_types=1);

namespace App\Service\VcsPlatform;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * What the VCS platforms share: their repository URLs (https or ssh, with or without ".git") and
 * how they say "not now" or "can't compare".
 */
abstract class AbstractVcsPlatform implements VcsPlatformInterface
{
    /**
     * @param string $host   e.g. "github.com"
     * @param bool   $nested whether a repository may sit in nested groups, as on GitLab
     */
    public function __construct(
        private readonly string $host,
        private readonly bool $nested = false,
        protected readonly string $observerUrl = '',
    ) {
    }

    public function repositoryOf(?string $url): ?string
    {
        $host = preg_quote($this->host, '~');
        $path = $this->nested ? '[\w.-]+(?:/[\w.-]+)+' : '[\w.-]+/[\w.-]+';
        $url = (string) preg_replace('~(?:\.git)?/?$~i', '', (string) $url);

        return 1 === preg_match('~^(?:https?://(?:www\.)?'.$host.'/|git@'.$host.':)('.$path.')$~i', $url, $match) ? $match[1] : null;
    }

    /**
     * @return bool false when the host can't compare the two commits, e.g. an unknown repository
     *
     * @throws RateLimitException
     */
    protected function isReadable(ResponseInterface $response, string $repository): bool
    {
        $status = $response->getStatusCode();
        $headers = $response->getHeaders(false);
        $remaining = $headers['x-ratelimit-remaining'][0] ?? $headers['ratelimit-remaining'][0] ?? null;
        if (429 === $status || (403 === $status && ('0' === $remaining || isset($headers['retry-after'])))) {
            throw new RateLimitException(sprintf('%s answered %d for %s', $this->host, $status, $repository));
        }

        // this pair only: e.g. a deleted or blocked repository, an unknown commit
        return !in_array($status, [403, 404, 422], true);
    }

    protected function userAgent(): string
    {
        return sprintf('CMS Observer (+%s)', $this->observerUrl);
    }

    /**
     * @param list<array{sha: string, subject: string, message: string, url: string}> $commits
     *
     * @return list<array{sha: string, subject: string, message: string, url: string}>|null
     */
    protected static function nonEmpty(array $commits): ?array
    {
        // two versions are at least one commit apart: none means the host didn't find them
        return [] === $commits ? null : $commits;
    }

    /**
     * @return array{sha: string, subject: string, message: string, url: string}
     */
    protected static function commit(string $sha, string $message, string $url): array
    {
        return ['sha' => $sha, 'subject' => trim(explode("\n", $message)[0]), 'message' => $message, 'url' => $url];
    }
}
