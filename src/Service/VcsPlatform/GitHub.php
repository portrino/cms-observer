<?php

declare(strict_types=1);

namespace App\Service\VcsPlatform;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * github.com, only with a token: without one GitHub allows 60 requests an hour. The token
 * needs no permissions, the repositories are public.
 */
class GitHub extends AbstractCompareVcsPlatform
{
    public function __construct(
        HttpClientInterface $githubClient,
        #[Autowire(env: 'DEFAULT_URI')]
        string $observerUrl,
        #[Autowire(env: 'GITHUB_API_TOKEN')]
        private readonly string $token,
    ) {
        parent::__construct($githubClient, 'github.com', $observerUrl);
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->token);
    }

    protected function authorization(): array
    {
        return ['Authorization' => 'Bearer '.trim($this->token)];
    }
}
