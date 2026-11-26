<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EndOfLifeRelease;
use App\Repository\EndOfLifeReleaseRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Syncs release-cycle support-window data from endoflife.date for the
 * runtimes this app cross-references: PHP and the database engines
 * (MariaDB, MySQL, PostgreSQL), plus TYPO3 for the support status of
 * the majors an extension version is compatible with. This is a small, fixed set of products
 * (unlike TYPO3 extensions), so it's just hardcoded here rather than derived
 * from reported data.
 */
class EndOfLifeReleaseSynchronizer
{
    private const array PRODUCTS = ['php', 'mariadb', 'mysql', 'postgresql', 'typo3', 'craft-cms'];

    public function __construct(
        private readonly EndOfLifeApiClient $apiClient,
        private readonly PhpReleaseApiClient $phpReleaseApiClient,
        private readonly EndOfLifeReleaseRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return int number of release cycles synced across all products
     */
    public function sync(): int
    {
        $synced = 0;

        foreach (self::PRODUCTS as $product) {
            $data = $this->apiClient->fetchProduct($product);

            foreach ($data['cycles'] as $cycleData) {
                $this->syncCycle($product, $cycleData, $data['eolUrl']);
                ++$synced;
            }
        }

        $this->entityManager->flush();

        return $synced;
    }

    /**
     * @param array{cycle: string, isEol: bool, latestVersion: string, extendedSupportEnded: ?bool} $cycleData
     */
    private function syncCycle(string $product, array $cycleData, string $eolUrl): void
    {
        $release = $this->repository->findOneByProductAndCycle($product, $cycleData['cycle']);

        if (null !== $release) {
            $release->update($cycleData['isEol'], $cycleData['latestVersion'], $eolUrl);
        } else {
            $release = new EndOfLifeRelease(
                $product,
                $cycleData['cycle'],
                $cycleData['isEol'],
                $cycleData['latestVersion'],
                $eolUrl,
            );
            $this->entityManager->persist($release);
        }

        $release->setExtendedSupportEnded($cycleData['extendedSupportEnded']);

        // EOL cycles count as insecure anyway, so only supported PHP cycles need the patch-level check.
        if ('php' === $product && !$cycleData['isEol']) {
            $release->setLatestSecurityVersion($this->phpReleaseApiClient->fetchLatestSecurityVersion($cycleData['cycle']));
        }
    }
}
