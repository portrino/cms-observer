<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\EndOfLifeApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class EndOfLifeApiClientTest extends TestCase
{
    public function testReadsExtendedSupportStateWhenTheProductHasOne(): void
    {
        // trimmed shape of https://endoflife.date/api/v1/products/typo3
        $client = new EndOfLifeApiClient(new MockHttpClient(new JsonMockResponse(['result' => [
            'links' => ['html' => 'https://endoflife.date/typo3'],
            'releases' => [
                ['name' => '12', 'isEol' => true, 'isEoes' => false, 'latest' => ['name' => '12.4.40']],
                ['name' => '10', 'isEol' => true, 'isEoes' => true, 'latest' => ['name' => '10.4.50']],
                ['name' => '8.4', 'isEol' => true, 'latest' => ['name' => '8.4.0']],
            ],
        ]])));

        $cycles = $client->fetchProduct('typo3')['cycles'];

        self::assertFalse($cycles[0]['extendedSupportEnded']);
        self::assertTrue($cycles[1]['extendedSupportEnded']);
        self::assertNull($cycles[2]['extendedSupportEnded']);
    }
}
