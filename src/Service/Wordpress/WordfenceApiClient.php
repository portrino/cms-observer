<?php

declare(strict_types=1);

namespace App\Service\Wordpress;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Wordfence Intelligence Vulnerability Data Feed v3
 * (https://www.wordfence.com/help/wordfence-intelligence/v3-accessing-and-consuming-the-vulnerability-data-feed/).
 * Free for commercial use with an API key; the endpoint always returns the
 * complete feed, so it is downloaded to a file instead of held in memory.
 */
class WordfenceApiClient
{
    private const string FEED_URL = 'https://www.wordfence.com/api/intelligence/v3/vulnerabilities/production';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'WORDFENCE_API_KEY')]
        private readonly string $apiKey,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->apiKey;
    }

    /**
     * @return int bytes written
     */
    public function downloadFeed(string $targetPath): int
    {
        $response = $this->httpClient->request('GET', self::FEED_URL, ['auth_bearer' => $this->apiKey]);
        $response->getHeaders(); // throws on 4xx/5xx (e.g. 429 rate limit) before anything is written

        $file = fopen($targetPath, 'w');
        if (false === $file) {
            throw new \RuntimeException(sprintf('Cannot write the Wordfence feed to "%s".', $targetPath));
        }

        $bytes = 0;
        try {
            foreach ($this->httpClient->stream($response) as $chunk) {
                $bytes += (int) fwrite($file, $chunk->getContent());
            }
        } finally {
            fclose($file);
        }

        return $bytes;
    }
}
