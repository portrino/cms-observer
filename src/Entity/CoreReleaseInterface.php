<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * A core release of one CMS, as SiteCoreVersionEvaluator rates a site's
 * core version against it.
 */
interface CoreReleaseInterface
{
    public function getVersion(): string;

    /** Leading major component, e.g. 13 for "13.4.28" */
    public function getMajor(): int;

    public function getLatestVersion(): string;

    public function isLatest(): bool;

    public function isInsecure(): bool;

    public function getReleaseDate(): ?\DateTimeImmutable;
}
