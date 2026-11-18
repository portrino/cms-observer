<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * php.net's public releases JSON API, no authentication needed. Each release
 * carries "tags", where "security" marks a release that fixed vulnerabilities.
 */
class PhpReleaseApiClient
{
    private const string URL = 'https://www.php.net/releases/index.php';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return string|null newest security release in the cycle (e.g. "8.4"), null if there is none
     */
    public function fetchLatestSecurityVersion(string $cycle): ?string
    {
        $releases = $this->httpClient->request('GET', self::URL, [
            'query' => ['json' => '', 'version' => $cycle, 'max' => 100],
        ])->toArray();

        $latest = null;
        foreach ($releases as $version => $release) {
            $version = (string) $version;
            if (in_array('security', $release['tags'] ?? [], true)
                && (null === $latest || version_compare($version, $latest, '>'))) {
                $latest = $version;
            }
        }

        return $latest;
    }
}
