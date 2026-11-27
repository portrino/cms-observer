<?php

declare(strict_types=1);

namespace App\Service\Typo3;

use App\Entity\Typo3\ExtensionAdvisory;
use App\Repository\Typo3\ExtensionAdvisoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Syncs official TYPO3 extension security advisories (TYPO3-EXT-SA-*) from
 * the TYPO3 Security Team's RSS feed. Unlike core releases, there is no JSON
 * API for this - the feed only lists title/link/date, so the actual affected
 * version range is scraped from each advisory's detail page.
 *
 * ponytail: HTML-scrapes the detail page for the "Component"/"Affected
 * Versions"/"Severity" fields since no structured API exists. This is
 * confirmed against several real advisories but will break if TYPO3 changes
 * that page template - if sync starts silently finding 0 new fields, check
 * the regexes here first. The RSS feed itself is also just a rolling recent
 * window (~30 items), not full history, so old advisories predating first
 * sync are never backfilled.
 */
class ExtensionAdvisorySynchronizer
{
    private const string FEED_URL = 'https://news.typo3.com/security/rss-security';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly ExtensionAdvisoryRepository $repository,
    ) {
    }

    /**
     * @return int number of newly-synced advisories
     */
    public function sync(): int
    {
        $synced = 0;

        foreach ($this->fetchExtensionAdvisoryItems() as $item) {
            if (null !== $this->repository->findOneByAdvisoryId($item['advisoryId'])) {
                continue;
            }

            $details = $this->fetchAdvisoryDetails($item['url']);
            if (null === $details) {
                continue;
            }

            $this->entityManager->persist(new ExtensionAdvisory(
                advisoryId: $item['advisoryId'],
                extensionKey: $details['extensionKey'],
                title: $item['title'],
                url: $item['url'],
                publishedAt: $item['publishedAt'],
                affectedVersionsRaw: $details['affectedVersionsRaw'],
                severity: $details['severity'],
                cveIds: $details['cveIds'],
            ));
            ++$synced;
        }

        $this->entityManager->flush();

        return $synced;
    }

    /**
     * @return array<int, array{advisoryId: string, title: string, url: string, publishedAt: \DateTimeImmutable}>
     */
    private function fetchExtensionAdvisoryItems(): array
    {
        $xml = $this->httpClient->request('GET', self::FEED_URL)->getContent();
        $rss = simplexml_load_string($xml);

        if (false === $rss) {
            return [];
        }

        $items = [];

        foreach ($rss->channel->item as $item) {
            $title = (string) $item->title;

            if (!str_starts_with($title, 'TYPO3-EXT-SA-')) {
                continue;
            }

            [$advisoryId] = explode(':', $title, 2);

            $items[] = [
                'advisoryId' => trim($advisoryId),
                'title' => $title,
                'url' => (string) $item->link,
                'publishedAt' => new \DateTimeImmutable((string) $item->pubDate),
            ];
        }

        return $items;
    }

    /**
     * @return array{extensionKey: string, severity: ?string, cveIds: ?string, affectedVersionsRaw: string}|null
     */
    private function fetchAdvisoryDetails(string $url): ?array
    {
        $html = $this->httpClient->request('GET', $url)->getContent();

        // [^<]* after the closing paren tolerates stray trailing characters in the
        // source markup (e.g. a duplicated closing quote seen on one live advisory).
        if (1 !== preg_match('/<span>Component: <\/span><a[^>]*><span>"[^"]*"\s*\(([a-z0-9_]+)\)[^<]*<\/span>/i', $html, $matches)) {
            return null;
        }
        $extensionKey = $matches[1];

        if (1 !== preg_match('/<span>Affected Versions:\s*([^<]*)<\/span>/i', $html, $matches)) {
            return null;
        }
        $affectedVersionsRaw = $this->normalizeText($matches[1]);

        $severity = null;
        if (1 === preg_match('/<span>Severity:\s*([^<]*)<\/span>/i', $html, $matches)) {
            $severity = $this->normalizeText($matches[1]);
        }

        $cveIds = null;
        if (preg_match_all('/CVE-\d{4}-\d+/', $html, $matches) > 0) {
            $cveIds = implode(', ', array_unique($matches[0]));
        }

        return [
            'extensionKey' => $extensionKey,
            'severity' => $severity,
            'cveIds' => $cveIds,
            'affectedVersionsRaw' => $affectedVersionsRaw,
        ];
    }

    /**
     * Decodes HTML entities (e.g. &nbsp;, &amp;) and collapses the result to
     * plain single spaces, since a literal non-breaking space would silently
     * break ExtensionUtility::matchesAffectedVersions()' whitespace-sensitive regexes.
     */
    private function normalizeText(string $text): string
    {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);

        // null on invalid UTF-8: keep the text rather than lose it
        return trim(preg_replace('/\s+/u', ' ', $decoded) ?? $decoded);
    }
}
