<?php

declare(strict_types=1);

namespace App\Service;

use Composer\InstalledVersions;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * The latest stable Observer release on GitHub, fetched daily by app:sync-observer-release,
 * for the update hint in the brand dropdown.
 */
class ObserverRelease
{
    private const string LATEST_URL = 'https://api.github.com/repos/portrino/cms-observer/releases/latest';
    private const string CACHE_KEY = 'observer_release';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheItemPoolInterface $cache,
        // null: the installed root package; set by tests, which run on a branch checkout
        private readonly ?string $installedVersion = null,
    ) {
    }

    /**
     * @return string the fetched version
     */
    public function sync(): string
    {
        // releases/latest skips drafts and pre-releases
        $release = $this->httpClient->request('GET', self::LATEST_URL)->toArray();
        $latest = ['version' => ltrim((string) $release['tag_name'], 'v'), 'url' => (string) $release['html_url']];

        $this->cache->save($this->cache->getItem(self::CACHE_KEY)->set($latest));

        return $latest['version'];
    }

    /**
     * Until the first sync, e.g. right after app:upgrade cleared the cache, there's no hint.
     *
     * @return array{version: string, url: string}|null
     */
    #[AsTwigFunction(name: 'observer_update')]
    public function availableUpdate(): ?array
    {
        $installed = $this->installedVersion ?? InstalledVersions::getRootPackage()['pretty_version'];
        /** @var array{version: string, url: string}|null $latest */
        $latest = $this->cache->getItem(self::CACHE_KEY)->get();

        // a branch checkout like dev-main has no version to compare
        if (null === $latest || str_starts_with($installed, 'dev-') || !version_compare($latest['version'], $installed, '>')) {
            return null;
        }

        return $latest;
    }
}
