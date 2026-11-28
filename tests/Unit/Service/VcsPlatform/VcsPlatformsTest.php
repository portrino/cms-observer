<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\VcsPlatform;

use App\Service\VcsPlatform\Codeberg;
use App\Service\VcsPlatform\GitHub;
use App\Service\VcsPlatform\GitLab;
use App\Service\VcsPlatform\VcsPlatforms;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

class VcsPlatformsTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, ?string, ?string}>
     */
    public static function urls(): iterable
    {
        yield 'GitHub https with .git' => ['https://github.com/b13/http2.git', GitHub::class, 'b13/http2'];
        yield 'GitHub ssh' => ['git@github.com:portrino/px_dbsequencer.git', GitHub::class, 'portrino/px_dbsequencer'];
        yield 'GitLab' => ['https://gitlab.com/vendor/extension.git', GitLab::class, 'vendor/extension'];
        yield 'GitLab in a subgroup' => ['https://gitlab.com/agency/typo3/extension.git', GitLab::class, 'agency/typo3/extension'];
        yield 'GitLab ssh' => ['git@gitlab.com:agency/typo3/extension.git', GitLab::class, 'agency/typo3/extension'];
        yield 'Codeberg' => ['https://codeberg.org/owner/extension', Codeberg::class, 'owner/extension'];
        yield 'Bitbucket' => ['https://bitbucket.org/vendor/package.git', null, null];
        yield 'a host that only starts like GitHub' => ['https://github.com.example.test/vendor/package.git', null, null];
        yield 'none' => [null, null, null];
    }

    #[DataProvider('urls')]
    public function testFindsTheHostAndRepositoryOfAUrl(?string $url, ?string $host, ?string $repository): void
    {
        $found = $this->hosts('token')->find($url);

        self::assertSame($host, null === $found ? null : $found[0]::class);
        self::assertSame($repository, $found[1] ?? null);
    }

    public function testGitHubOnlyWithAToken(): void
    {
        self::assertNull($this->hosts(' ')->find('https://github.com/b13/http2.git'));
        self::assertNotNull($this->hosts(' ')->find('https://gitlab.com/vendor/extension.git'), 'GitLab needs none');
    }

    private function hosts(string $gitHubToken): VcsPlatforms
    {
        $http = new MockHttpClient();

        return new VcsPlatforms([new GitHub($http, 'https://observer.test', $gitHubToken), new GitLab($http, 'https://observer.test'), new Codeberg($http, 'https://observer.test')]);
    }
}
