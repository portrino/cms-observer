<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Site;
use App\Repository\EndOfLifeReleaseRepository;

/**
 * Cross-references a site's reported PHP and database version against
 * synced endoflife.date data (EndOfLifeRelease) to determine whether each is
 * insecure (past end of life, or for PHP: older than the cycle's latest
 * security release) and/or outdated (a newer release in the same cycle exists).
 */
class SiteRuntimeVersionEvaluator
{
    public function __construct(
        private readonly EndOfLifeReleaseRepository $releaseRepository,
        private readonly SecurityNotifier $securityNotifier,
    ) {
    }

    public function evaluate(Site $site): void
    {
        $this->evaluatePhp($site);
        $this->evaluateDb($site);
    }

    private function evaluatePhp(Site $site): void
    {
        $version = $site->getCurrentPhpVersion();
        if (null === $version) {
            return;
        }

        $wasInsecure = $site->isPhpInsecure();
        $release = $this->releaseRepository->findOneByProductAndCycle('php', $this->cycleOf($version));

        if (null === $release) {
            $site->setPhpStatus(null, null, null, null);

            return;
        }

        $securityVersion = $release->getLatestSecurityVersion();
        $missesSecurityFixes = null !== $securityVersion && version_compare($version, $securityVersion, '<');
        $insecure = $release->isEol() || $missesSecurityFixes;

        // endoflife.date lags behind new releases, so a newer installed version is not outdated
        $outdated = version_compare($version, $release->getLatestVersion(), '<');
        $site->setPhpStatus($insecure, $outdated, $outdated ? $release->getLatestVersion() : null, $release->getEolUrl());

        if ($insecure && true !== $wasInsecure) {
            $this->securityNotifier->addFinding($site, sprintf('PHP %s', $version), $release->isEol()
                ? 'PHP version is now insecure (past end of life).'
                : sprintf('PHP version is missing security fixes (fixed in %s).', $securityVersion));
        }
    }

    private function evaluateDb(Site $site): void
    {
        $version = $site->getCurrentDbVersion();
        if (null === $version) {
            return;
        }

        $wasInsecure = $site->isDbInsecure();
        // Rows stored before the platform was recorded hold the raw version, normalize() handles both
        [$product, $plainVersion] = DbVersionNormalizer::normalize($site->getCurrentDbPlatform(), $version);
        $release = $this->releaseRepository->findOneByProductAndCycle($product, $this->cycleOf($plainVersion, $product));

        if (null === $release) {
            $site->setDbStatus(null, null, null, null);

            return;
        }

        $outdated = version_compare($plainVersion, $release->getLatestVersion(), '<');
        $site->setDbStatus($release->isEol(), $outdated, $outdated ? $release->getLatestVersion() : null, $release->getEolUrl());

        if ($release->isEol() && true !== $wasInsecure) {
            $this->securityNotifier->addFinding($site, sprintf('Database %s', $version), 'Database version is now insecure (past end of life).');
        }
    }

    /**
     * @param iterable<Site> $sites
     */
    public function evaluateAll(iterable $sites): void
    {
        foreach ($sites as $site) {
            $this->evaluate($site);
        }
    }

    private function cycleOf(string $version, string $product = 'php'): string
    {
        $parts = explode('.', $version);

        // endoflife.date names PostgreSQL cycles by major only since 10 ("16"), before that "9.6"
        if ('postgresql' === $product && (int) $parts[0] >= 10) {
            return $parts[0];
        }

        return $parts[0].'.'.($parts[1] ?? '0');
    }
}
