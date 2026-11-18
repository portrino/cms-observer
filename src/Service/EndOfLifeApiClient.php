<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin wrapper around endoflife.date's public v1 API
 * (https://endoflife.date/docs/api/v1/), no authentication needed.
 */
class EndOfLifeApiClient
{
    private const string BASE_URL = 'https://endoflife.date/api/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array{eolUrl: string, cycles: array<int, array{cycle: string, isEol: bool, latestVersion: string, extendedSupportEnded: ?bool}>}
     */
    public function fetchProduct(string $product): array
    {
        $result = $this->httpClient->request('GET', self::BASE_URL."/products/{$product}")->toArray()['result'];

        $cycles = [];
        foreach ($result['releases'] as $release) {
            if (!isset($release['latest']['name'])) {
                continue;
            }

            $cycles[] = [
                'cycle' => (string) $release['name'],
                'isEol' => (bool) $release['isEol'],
                'latestVersion' => (string) $release['latest']['name'],
                'extendedSupportEnded' => isset($release['isEoes']) ? (bool) $release['isEoes'] : null,
            ];
        }

        return [
            'eolUrl' => (string) $result['links']['html'],
            'cycles' => $cycles,
        ];
    }
}
