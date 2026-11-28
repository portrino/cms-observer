<?php

declare(strict_types=1);

namespace App\Service\VcsPlatform;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** codeberg.org (Forgejo), public repositories without a token */
class Codeberg extends AbstractCompareVcsPlatform
{
    public function __construct(
        HttpClientInterface $codebergClient,
        #[Autowire(env: 'DEFAULT_URI')]
        string $observerUrl,
    ) {
        parent::__construct($codebergClient, 'codeberg.org', $observerUrl);
    }

    public function isConfigured(): bool
    {
        return true;
    }
}
