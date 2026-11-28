<?php

declare(strict_types=1);

namespace App\Service\VcsPlatform;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A public VCS platform whose API lists the commits between two versions (ADR-0007), by the
 * repository Packagist names for a version.
 */
#[AutoconfigureTag]
interface VcsPlatformInterface
{
    /** The repository path on this host, e.g. "b13/http2", null for a URL of another host */
    public function repositoryOf(?string $url): ?string;

    /** Whether it can be asked, e.g. GitHub only with a token */
    public function isConfigured(): bool;

    /**
     * The commits after $base up to $head, with their first line and whole message.
     *
     * @return list<array{sha: string, subject: string, message: string, url: string}>|null null when
     *                                                                                      the host can't compare them, e.g. a deleted repository or a moved tag
     *
     * @throws RateLimitException when the host declines further requests for now
     */
    public function commitsBetween(string $repository, string $base, string $head): ?array;
}
