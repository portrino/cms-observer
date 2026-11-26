<?php

declare(strict_types=1);

namespace App\Service\Craft;

use Composer\Semver\VersionParser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Versions and security advisories of Composer packages from Packagist
 * (https://packagist.org/apidoc). Packagist asks API users for a User-Agent with
 * a contact address, at most a few parallel requests and no jobs at the full hour.
 */
class PackagistApiClient
{
    // keeps the GET URL of one advisory request short
    private const int ADVISORY_PACKAGES_PER_REQUEST = 50;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'DEFAULT_URI')]
        private readonly string $observerUrl,
        #[Autowire(env: 'PACKAGIST_CONTACT_EMAIL')]
        private readonly string $contactEmail,
    ) {
    }

    /**
     * Stable versions of a package, newest first, with the Craft CMS version they require.
     *
     * @return list<array{version: string, releaseDate: ?\DateTimeImmutable, craftConstraint: ?string}>|null null if Packagist doesn't know the package
     */
    public function versions(string $packageName): ?array
    {
        // shortcut: fetches the whole p2 file every run (gzip: ~80 KB core, ~25 KB per plugin) instead of
        // If-Modified-Since with a stored Last-Modified; upgrade at hundreds of plugins or shorter sync intervals
        $response = $this->httpClient->request('GET', 'https://repo.packagist.org/p2/'.$packageName.'.json', ['headers' => $this->headers()]);
        if (404 === $response->getStatusCode()) {
            return null;
        }

        return array_values(array_filter(array_map(self::stableVersion(...), self::expand($response->toArray()['packages'][$packageName] ?? []))));
    }

    /**
     * @param array<string, mixed> $data one expanded version of a p2 file
     *
     * @return array{version: string, releaseDate: ?\DateTimeImmutable, craftConstraint: ?string}|null null for a pre-release or dev version
     */
    private static function stableVersion(array $data): ?array
    {
        $version = is_string($data['version'] ?? null) ? $data['version'] : '';
        if ('stable' !== VersionParser::parseStability($version)) {
            return null;
        }
        $require = is_array($data['require'] ?? null) ? $data['require'] : [];

        return [
            'version' => $version,
            'releaseDate' => is_string($data['time'] ?? null) ? new \DateTimeImmutable($data['time']) : null,
            'craftConstraint' => is_string($require['craftcms/cms'] ?? null) ? $require['craftcms/cms'] : null,
        ];
    }

    /**
     * Security advisories of the given packages; packages without any are missing.
     *
     * @param list<string> $packageNames
     *
     * @return array<string, list<array<string, mixed>>> keyed by package name
     */
    public function advisories(array $packageNames): array
    {
        $advisories = [];
        foreach (array_chunk(array_values(array_unique($packageNames)), self::ADVISORY_PACKAGES_PER_REQUEST) as $chunk) {
            $response = $this->httpClient->request('GET', 'https://packagist.org/api/security-advisories/', [
                'headers' => $this->headers(),
                'query' => ['packages' => $chunk],
            ]);
            foreach ($response->toArray()['advisories'] ?? [] as $packageName => $list) {
                if (is_string($packageName) && is_array($list) && [] !== $list) {
                    $advisories[$packageName] = array_values(array_filter($list, is_array(...)));
                }
            }
        }

        return $advisories;
    }

    /**
     * Packagist's p2 files are "composer/2.0" minified: each version only lists what changed
     * against the one before, "__unset" removes a key (Composer's MetadataMinifier::expand()).
     *
     * @param array<int, array<string, mixed>> $versions
     *
     * @return list<array<string, mixed>>
     */
    private static function expand(array $versions): array
    {
        $expanded = [];
        $current = [];
        foreach ($versions as $changes) {
            foreach ($changes as $key => $value) {
                if ('__unset' === $value) {
                    unset($current[$key]);
                } else {
                    $current[$key] = $value;
                }
            }
            $expanded[] = $current;
        }

        return $expanded;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $contact = '' !== trim($this->contactEmail) ? '; mailto:'.trim($this->contactEmail) : '';

        return ['User-Agent' => sprintf('CMS Observer (+%s%s)', $this->observerUrl, $contact)];
    }
}
