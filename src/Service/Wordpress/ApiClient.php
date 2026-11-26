<?php

declare(strict_types=1);

namespace App\Service\Wordpress;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Thin wrapper around WordPress.org's public endpoints, no authentication
 * required for any of them:
 * - api.wordpress.org/core/stable-check/1.0/ (all known core releases with
 *   their status - "insecure"/"outdated"/"latest")
 * - api.wordpress.org/plugins/info/1.2/ (per-plugin metadata; only the
 *   latest published version's requires/tested compatibility is exposed,
 *   and there's no security/vulnerability field at all)
 * - plugins.svn.wordpress.org (the actual plugin source, read-only mirror of
 *   the same SVN repo the .org plugin directory publishes from) - used to
 *   fill the gaps the two API endpoints above leave: a release date per
 *   version (via a WebDAV PROPFIND on the tags/ directory) and each
 *   version's minimum WordPress requirement (by reading its bootstrap
 *   file's plugin header comment).
 * - core.svn.wordpress.org (WordPress core's own SVN mirror) - same
 *   PROPFIND trick, for core release dates, since stable-check doesn't
 *   carry them either.
 *
 * Both *.svn.wordpress.org hosts (unlike api.wordpress.org) 403 any request
 * without a User-Agent header, so one is set explicitly on every request
 * this client makes.
 */
class ApiClient
{
    private const string STABLE_CHECK_URL = 'https://api.wordpress.org/core/stable-check/1.0/';
    private const string PLUGIN_INFO_URL = 'https://api.wordpress.org/plugins/info/1.2/';
    private const string THEME_INFO_URL = 'https://api.wordpress.org/themes/info/1.2/';
    private const string PLUGIN_SVN_BASE_URL = 'https://plugins.svn.wordpress.org';
    private const string CORE_SVN_TAGS_URL = 'https://core.svn.wordpress.org/tags/';
    private const string USER_AGENT = 'cms-observer (+https://github.com/portrino/cms-observer)';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, string> status ("insecure"|"outdated"|"latest") keyed by version
     */
    public function getCoreReleaseStatuses(): array
    {
        $data = $this->request('GET', self::STABLE_CHECK_URL)->toArray();

        $statuses = [];
        foreach ($data as $version => $status) {
            $statuses[(string) $version] = (string) $status;
        }

        return $statuses;
    }

    /**
     * @return array{version: string, requires: ?string, tested: ?string, description: ?string}|null null if the slug is unknown to WordPress.org
     */
    public function getPluginInfo(string $slug): ?array
    {
        $response = $this->request('GET', self::PLUGIN_INFO_URL, [
            'query' => [
                'action' => 'plugin_information',
                'slug' => $slug,
            ],
        ]);

        if (404 === $response->getStatusCode()) {
            return null;
        }

        try {
            $data = $response->toArray();
        } catch (ClientExceptionInterface $e) {
            // not the usual unknown slug (404 or no version) but e.g. a block or rate limit: the existing data stays and the next sync tries again
            $this->logClientError('plugin', $slug, $e);

            return null;
        }

        if (!isset($data['version'])) {
            return null;
        }

        $rawDescription = $data['sections']['description'] ?? '';

        return [
            'version' => (string) $data['version'],
            'requires' => ($data['requires'] ?? '') !== '' ? (string) $data['requires'] : null,
            'tested' => ($data['tested'] ?? '') !== '' ? (string) $data['tested'] : null,
            'description' => '' !== $rawDescription ? $this->toPlainText((string) $rawDescription) : null,
        ];
    }

    /**
     * @return array{version: string, requires: ?string}|null null if the slug is unknown to WordPress.org, e.g. a premium theme
     */
    public function getThemeInfo(string $slug): ?array
    {
        $response = $this->request('GET', self::THEME_INFO_URL, [
            'query' => [
                'action' => 'theme_information',
                'request' => ['slug' => $slug],
            ],
        ]);

        if (404 === $response->getStatusCode()) {
            return null;
        }

        try {
            $data = $response->toArray();
        } catch (ClientExceptionInterface $e) {
            // not the usual unknown slug (404 or no version) but e.g. a block or rate limit: the existing data stays and the next sync tries again
            $this->logClientError('theme', $slug, $e);

            return null;
        }

        if (!isset($data['version'])) {
            return null;
        }

        // themes without the header report false here
        $requires = $data['requires'] ?? null;

        return [
            'version' => (string) $data['version'],
            'requires' => is_string($requires) && '' !== $requires ? $requires : null,
        ];
    }

    /**
     * Release date per plugin tag, from a WebDAV PROPFIND on the plugin's
     * SVN tags/ directory (one request covers every tag) - the tag folder's
     * own last-modified date, which for an SVN tag copy is effectively its
     * release date.
     *
     * @return array<string, \DateTimeImmutable> keyed by version
     */
    public function getTagDates(string $slug): array
    {
        return $this->getTagDatesFromUrl(self::PLUGIN_SVN_BASE_URL."/{$slug}/tags/");
    }

    /**
     * Same as getTagDates(), for WordPress core's own SVN tags/ directory
     * (core.svn.wordpress.org) rather than a plugin's.
     *
     * @return array<string, \DateTimeImmutable> keyed by version
     */
    public function getCoreTagDates(): array
    {
        return $this->getTagDatesFromUrl(self::CORE_SVN_TAGS_URL);
    }

    /**
     * @return array<string, \DateTimeImmutable> keyed by version
     */
    private function getTagDatesFromUrl(string $tagsUrl): array
    {
        $xml = $this->fetchTagListing($tagsUrl);
        $entries = $xml?->xpath('//D:response');

        $dates = [];
        foreach (is_array($entries) ? $entries : [] as $entry) {
            $tag = $this->tagOf($entry);
            if (null === $tag) {
                continue;
            }

            [$version, $lastModified] = $tag;
            try {
                $dates[$version] = new \DateTimeImmutable($lastModified);
            } catch (\Exception $e) {
                $this->logger->warning('Unreadable release date of {version} at {url}, skipped: {message}', [
                    'version' => $version,
                    'url' => $tagsUrl,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }

        return $dates;
    }

    /**
     * The WebDAV listing of an SVN tags/ directory, null if there is none or it isn't XML.
     */
    private function fetchTagListing(string $tagsUrl): ?\SimpleXMLElement
    {
        $response = $this->request('PROPFIND', $tagsUrl, [
            'headers' => ['Depth' => '1'],
        ]);

        if ($response->getStatusCode() >= 400) {
            return null;
        }

        try {
            // no libxml warnings on top of the exception, which is logged below
            $xml = new \SimpleXMLElement($response->getContent(), \LIBXML_NOERROR | \LIBXML_NOWARNING);
        } catch (\Throwable $e) {
            $this->logger->warning('Unreadable SVN tag listing at {url}, no release dates taken from it: {message}', [
                'url' => $tagsUrl,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return null;
        }

        $xml->registerXPathNamespace('D', 'DAV:');

        return $xml;
    }

    /**
     * @return array{string, string}|null version and last-modified date of a tag folder
     */
    private function tagOf(\SimpleXMLElement $entry): ?array
    {
        $href = $this->firstNode($entry, 'D:href');
        $lastModified = $this->firstNode($entry, './/D:getlastmodified');
        if (null === $href || null === $lastModified) {
            return null;
        }

        $segments = explode('/', trim((string) $href, '/'));
        $version = end($segments);

        // the tags/ collection itself, not a version subfolder
        return 'tags' === $version ? null : [$version, (string) $lastModified];
    }

    /**
     * Finds the plugin's bootstrap file name within one version's tag
     * folder - almost always "{$slug}.php", but not always (e.g.
     * contact-form-7's is wp-contact-form-7.php, wordpress-seo's is
     * wp-seo.php) - so the slug-named file is tried first, falling back to
     * listing the tag's root and probing each top-level .php file for a
     * "Plugin Name:" header. The result is stable across a plugin's
     * versions, so callers should resolve it once (e.g. against the latest
     * version) and reuse it rather than repeating this discovery per version.
     */
    public function findBootstrapFilename(string $slug, string $version): ?string
    {
        $candidate = "{$slug}.php";
        if ($this->looksLikeBootstrapFile($slug, $version, $candidate)) {
            return $candidate;
        }

        $response = $this->request('GET', self::PLUGIN_SVN_BASE_URL."/{$slug}/tags/{$version}/");
        if ($response->getStatusCode() >= 400) {
            return null;
        }

        $matchCount = preg_match_all('/href="([^"\/]+\.php)"/', $response->getContent(false), $matches);
        if (false === $matchCount || 0 === $matchCount) {
            return null;
        }

        foreach ($matches[1] as $phpFile) {
            if ($phpFile === $candidate) {
                continue;
            }

            if ($this->looksLikeBootstrapFile($slug, $version, $phpFile)) {
                return $phpFile;
            }
        }

        return null;
    }

    /**
     * The "Requires at least" value from one version's bootstrap file
     * header comment - the closest thing WordPress.org exposes to a
     * per-version compatibility declaration (there's no equivalent of TER's
     * per-version typo3_versions list).
     */
    public function getRequiresAtLeast(string $slug, string $bootstrapFilename, string $version): ?string
    {
        $content = $this->fetchBootstrapContent($slug, $bootstrapFilename, $version);
        if (null === $content) {
            return null;
        }

        return $this->parseRequiresAtLeast($content);
    }

    private function looksLikeBootstrapFile(string $slug, string $version, string $filename): bool
    {
        $content = $this->fetchBootstrapContent($slug, $filename, $version);

        return null !== $content && 1 === preg_match('/^[ \t\/*#@]*Plugin Name:/mi', $content);
    }

    private function fetchBootstrapContent(string $slug, string $filename, string $version): ?string
    {
        $response = $this->request('GET', self::PLUGIN_SVN_BASE_URL."/{$slug}/tags/{$version}/{$filename}");
        if ($response->getStatusCode() >= 400) {
            return null;
        }

        // Plugin headers live in the first few KB - never pull a whole plugin file into memory.
        return substr($response->getContent(false), 0, 8192);
    }

    private function parseRequiresAtLeast(string $bootstrapContent): ?string
    {
        if (1 === preg_match('/^[ \t\/*#@]*Requires at least:\s*([0-9][0-9.]*)/mi', $bootstrapContent, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function firstNode(\SimpleXMLElement $element, string $path): ?\SimpleXMLElement
    {
        $nodes = $element->xpath($path);

        return is_array($nodes) ? ($nodes[0] ?? null) : null;
    }

    private function toPlainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function logClientError(string $kind, string $slug, ClientExceptionInterface $e): void
    {
        $this->logger->error('WordPress.org answered {status} for the {kind} {slug}, kept the known data', [
            'status' => $e->getResponse()->getStatusCode(),
            'kind' => $kind,
            'slug' => $slug,
        ]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $options['headers'] = array_merge($options['headers'] ?? [], ['User-Agent' => self::USER_AGENT]);

        return $this->httpClient->request($method, $url, $options);
    }
}
