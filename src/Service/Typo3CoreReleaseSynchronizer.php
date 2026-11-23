<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Typo3CoreRelease;
use App\Entity\Typo3ReleaseType;
use App\Repository\Typo3CoreReleaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Syncs TYPO3 core release data from the official get.typo3.org v1 API. This
 * is a single global feed shared by every monitored TYPO3 site, so it's
 * synced once here rather than by each site's client extension.
 *
 * A release is "insecure" if its major/LTS line's official maintenance
 * window (get.typo3.org/api/v1/major/'s maintained_until) has passed, or if
 * a later patch in the same major.minor line was itself a security release
 * (ported from georgringer/t3monitoring's release-freshness logic).
 */
class Typo3CoreReleaseSynchronizer
{
    private const string RELEASES_URL = 'https://get.typo3.org/api/v1/release/';
    private const string MAJORS_URL = 'https://get.typo3.org/api/v1/major/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly Typo3CoreReleaseRepository $repository,
    ) {
    }

    public function sync(): int
    {
        $majors = $this->fetchMajors();
        $releases = $this->parseReleases($this->fetchReleases(), $majors);

        foreach ($releases as $version => $data) {
            $existing = $this->repository->findOneByVersion($version);
            if (null !== $existing) {
                $existing->update($data['releaseDate'], $data['type'], $data['latestVersion'], $data['isActive'], $data['maintainedUntil'], $data['insecure'], $data['eltsUntil'], $data['ltsReleaseDate']);
            } else {
                $this->entityManager->persist(new Typo3CoreRelease(
                    $version,
                    $data['releaseDate'],
                    $data['type'],
                    $data['latestVersion'],
                    $data['isActive'],
                    $data['maintainedUntil'],
                    $data['insecure'],
                    $data['eltsUntil'],
                    $data['ltsReleaseDate'],
                ));
            }
        }

        $this->entityManager->flush();

        return count($releases);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchReleases(): array
    {
        return $this->httpClient->request('GET', self::RELEASES_URL)->toArray();
    }

    /**
     * Keyed by major-line version string, e.g. "13.0" (covers TYPO3 13's
     * whole development+LTS history) or "6.2" (pre-single-LTS-per-major era,
     * where each minor like 6.0/6.1/6.2 had its own independent line). The
     * key type is int|string, not just string, because PHP normalizes a
     * purely-numeric string key to int - number_format() can't statically
     * rule that out even though it never actually happens here (every line
     * has a decimal point).
     *
     * @return array<int|string, array{maintainedUntil: ?\DateTimeImmutable, eltsUntil: ?\DateTimeImmutable, ltsReleaseDate: ?\DateTimeImmutable}>
     */
    private function fetchMajors(): array
    {
        $data = $this->httpClient->request('GET', self::MAJORS_URL)->toArray();
        $majors = [];

        foreach ($data as $major) {
            if (!isset($major['version'])) {
                continue;
            }

            $key = number_format((float) $major['version'], 1, '.', '');
            $majors[$key] = [
                'maintainedUntil' => isset($major['maintained_until']) ? new \DateTimeImmutable((string) $major['maintained_until']) : null,
                'eltsUntil' => isset($major['elts_until']) ? new \DateTimeImmutable((string) $major['elts_until']) : null,
                // the major's release_date is the one of its LTS version
                'ltsReleaseDate' => isset($major['release_date']) ? new \DateTimeImmutable((string) $major['release_date']) : null,
            ];
        }

        return $majors;
    }

    /**
     * @param array<int, array<string, mixed>>                                                                                                    $rawReleases
     * @param array<int|string, array{maintainedUntil: ?\DateTimeImmutable, eltsUntil: ?\DateTimeImmutable, ltsReleaseDate: ?\DateTimeImmutable}> $majors
     *
     * @return array<string, array{releaseDate: \DateTimeImmutable, type: Typo3ReleaseType, latestVersion: string, isActive: bool, maintainedUntil: ?\DateTimeImmutable, eltsUntil: ?\DateTimeImmutable, ltsReleaseDate: ?\DateTimeImmutable, insecure: bool}>
     */
    private function parseReleases(array $rawReleases, array $majors): array
    {
        $releases = [];
        $latestByLine = [];
        $now = new \DateTimeImmutable();

        foreach ($rawReleases as $item) {
            $version = (string) ($item['version'] ?? '');
            $parts = explode('.', $version);
            if (3 !== count($parts)) {
                continue;
            }
            [$major, $minor] = $parts;
            $line = "{$major}.{$minor}";

            $type = Typo3ReleaseType::tryFrom((string) ($item['type'] ?? '')) ?? Typo3ReleaseType::Regular;

            // TYPO3 8+ has one major-list entry per whole major number (e.g. "13.0"
            // covers the entire 13.x development+LTS history); TYPO3 <=7 had each
            // minor line (6.0/6.1/6.2, 4.0-4.7, ...) independently maintained.
            $majorData = $majors[$line] ?? $majors["{$major}.0"] ?? null;
            $maintainedUntil = $majorData['maintainedUntil'] ?? null;
            $isActive = null !== $maintainedUntil && $maintainedUntil >= $now && Typo3ReleaseType::Development !== $type;

            $releases[$version] = [
                'releaseDate' => new \DateTimeImmutable((string) $item['date']),
                'type' => $type,
                'isActive' => $isActive,
                'maintainedUntil' => $maintainedUntil,
                'eltsUntil' => $majorData['eltsUntil'] ?? null,
                'ltsReleaseDate' => $majorData['ltsReleaseDate'] ?? null,
                'insecure' => false,
                'line' => $line,
            ];

            if (!isset($latestByLine[$line]) || version_compare($version, $latestByLine[$line], '>')) {
                $latestByLine[$line] = $version;
            }
        }

        foreach ($releases as &$data) {
            $data['latestVersion'] = $latestByLine[$data['line']];
            unset($data['line']);
        }
        unset($data);

        $this->flagInsecureReleases($releases);

        return $releases;
    }

    /**
     * @param array<string, array{releaseDate: \DateTimeImmutable, type: Typo3ReleaseType, latestVersion: string, isActive: bool, maintainedUntil: ?\DateTimeImmutable, eltsUntil: ?\DateTimeImmutable, ltsReleaseDate: ?\DateTimeImmutable, insecure: bool}> $releases
     */
    private function flagInsecureReleases(array &$releases): void
    {
        foreach ($releases as &$release) {
            if (!$release['isActive']) {
                $release['insecure'] = true;
            }
        }
        unset($release);

        $securityVersions = [];
        foreach ($releases as $version => $release) {
            if ($release['isActive'] && Typo3ReleaseType::Security === $release['type']) {
                $securityVersions[] = $version;
            }
        }

        foreach ($securityVersions as $version) {
            $parts = explode('.', $version);
            if (3 !== count($parts)) {
                continue;
            }

            [$major, $minor, $patch] = $parts;
            for ($i = 0; $i < (int) $patch; ++$i) {
                $earlierVersion = sprintf('%s.%s.%d', $major, $minor, $i);
                if (isset($releases[$earlierVersion])) {
                    $releases[$earlierVersion]['insecure'] = true;
                }
            }
        }
    }
}
