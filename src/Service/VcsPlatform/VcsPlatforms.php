<?php

declare(strict_types=1);

namespace App\Service\VcsPlatform;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Finds the VCS platform of a repository URL among the ones that can be asked.
 */
class VcsPlatforms
{
    /**
     * @param iterable<VcsPlatformInterface> $hosts
     */
    public function __construct(
        #[AutowireIterator(VcsPlatformInterface::class)]
        private readonly iterable $hosts,
    ) {
    }

    /**
     * @return array{VcsPlatformInterface, string}|null the host and the repository on it, null for another
     *                                                  host or one that can't be asked, e.g. GitHub without a token
     */
    public function find(?string $url): ?array
    {
        foreach ($this->hosts as $host) {
            $repository = $host->repositoryOf($url);
            if (null !== $repository && $host->isConfigured()) {
                return [$host, $repository];
            }
        }

        return null;
    }
}
