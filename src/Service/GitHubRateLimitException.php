<?php

declare(strict_types=1);

namespace App\Service;

/** GitHub declines further requests for now, e.g. the token's hourly limit is used up. */
final class GitHubRateLimitException extends \RuntimeException
{
}
