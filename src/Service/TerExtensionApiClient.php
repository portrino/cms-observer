<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin wrapper around TER's public REST API (extensions.typo3.org/api/v1),
 * per https://extensions.typo3.org/api/v1/docs. Read endpoints used here
 * require no authentication. Rate limited to 100 requests per window.
 */
class TerExtensionApiClient
{
    private const string BASE_URL = 'https://extensions.typo3.org/api/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Every published version of one extension, oldest first (TER's own
     * order) - one request regardless of how many versions exist, which is
     * why this backs both the insecure/outdated cache and the "newer
     * versions" table rather than looking up versions one at a time.
     *
     * @return array<int, array{version: string, title: string, description: ?string, uploadComment: ?string, state: ?string, typo3Versions: ?string, uploadDate: ?\DateTimeImmutable, insecure: bool}>
     */
    public function getVersionHistory(string $extensionKey): array
    {
        $response = $this->httpClient->request('GET', self::BASE_URL."/extension/{$extensionKey}/versions");

        if (404 === $response->getStatusCode()) {
            return [];
        }

        // The endpoint wraps the version list in an extra array: [[v1, v2, ...]].
        $versions = $response->toArray()[0] ?? [];

        $history = [];
        foreach ($versions as $version) {
            if (!isset($version['number'])) {
                continue;
            }

            $typo3Versions = $version['typo3_versions'] ?? [];

            $history[] = [
                'version' => (string) $version['number'],
                'title' => (string) ($version['title'] ?? ''),
                'description' => '' !== $version['description'] ? ($version['description'] ?? null) : null,
                'uploadComment' => $version['upload_comment'] ?? null,
                'state' => $version['state'] ?? null,
                'typo3Versions' => [] !== $typo3Versions ? implode(', ', $typo3Versions) : null,
                'uploadDate' => isset($version['upload_date']) ? new \DateTimeImmutable()->setTimestamp((int) $version['upload_date']) : null,
                'insecure' => ((int) ($version['review_state'] ?? 0)) === -1,
            ];
        }

        return $history;
    }

    /**
     * Packagist/repository/manual links for one extension key, from TER's
     * per-extension overview endpoint (distinct from the /versions endpoint
     * used by getVersionHistory()). Null fields mean TER doesn't have that
     * link set for this extension.
     *
     * @return array{packagistUrl: ?string, repositoryUrl: ?string, manualUrl: ?string}|null null if the extension key is unknown to TER
     */
    public function getExtensionMeta(string $extensionKey): ?array
    {
        $response = $this->httpClient->request('GET', self::BASE_URL."/extension/{$extensionKey}");

        if (404 === $response->getStatusCode()) {
            return null;
        }

        $data = $response->toArray()[0] ?? null;
        if (null === $data) {
            return null;
        }

        $meta = $data['meta'] ?? [];

        return [
            'packagistUrl' => ($meta['packagist'] ?? '') !== '' ? $meta['packagist'] : null,
            'repositoryUrl' => ($meta['repository_url'] ?? '') !== '' ? $meta['repository_url'] : null,
            'manualUrl' => ($meta['external_manual'] ?? '') !== '' ? $meta['external_manual'] : null,
        ];
    }

    /**
     * @param string[] $extensionKeys
     *
     * @return array<string, string> latest version number keyed by extension key
     */
    public function getLatestVersions(array $extensionKeys): array
    {
        if ([] === $extensionKeys) {
            return [];
        }

        $response = $this->httpClient->request('POST', self::BASE_URL.'/extension/versions/latest', [
            'body' => ['keys' => array_values($extensionKeys)],
        ]);

        $data = $response->toArray();

        $latest = [];
        foreach ($data as $key => $version) {
            if (isset($version['number'])) {
                $latest[$key] = (string) $version['number'];
            }
        }

        return $latest;
    }
}
