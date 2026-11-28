<?php

declare(strict_types=1);

namespace App\Service\VcsPlatform;

/** A VCS platform declines further requests for now, e.g. its hourly limit is used up. */
final class RateLimitException extends \RuntimeException
{
}
