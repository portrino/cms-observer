<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service\Typo3;

use App\Entity\CmsType;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Entity\Typo3\PackagistVersion;
use App\Repository\SitePackageRepository;
use App\Repository\Typo3\PackagistVersionRepository;
use App\Service\GitHubApiClient;
use App\Service\Typo3\BreakingCommitSynchronizer;
use App\Service\Typo3\ExtensionVersionCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

class BreakingCommitSynchronizerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $site = new Site('TYPO3', CmsType::Typo3, 'https://typo3.test', 'hash-breaking-commits');
        $this->entityManager->persist($site);
        $this->entityManager->persist(new SitePackage($site, 'http2', 'HTTP/2', '3.0.0', true, 'b13/http2'));
        foreach (['2.0.0' => 'ref-2', '3.0.0' => 'ref-3', '4.0.0' => 'ref-4', 'v4.1.0' => 'ref-41'] as $version => $reference) {
            $this->entityManager->persist(new PackagistVersion('b13/http2', $version, null, [13], 'https://github.com/b13/http2.git', $reference));
        }
        $this->entityManager->flush();
    }

    public function testKeepsTheBreakingCommitsOfEachVersionNewerThanTheInstalledOne(): void
    {
        $compared = [];
        $synchronizer = $this->synchronizer(new MockHttpClient(static function (string $method, string $url) use (&$compared): JsonMockResponse {
            $compared[] = (string) preg_replace('~^.*/compare/([^?]+).*$~', '$1', $url);

            return new JsonMockResponse(['commits' => str_contains($url, 'ref-3...ref-4') ? [
                ['sha' => 'aaa', 'html_url' => 'https://github.com/b13/http2/commit/aaa', 'commit' => ['message' => '!!![TASK] change requirements']],
                ['sha' => 'bbb', 'html_url' => 'https://github.com/b13/http2/commit/bbb', 'commit' => ['message' => "Merge pull request #35 from b13/task/v14\n\n!!![TASK] change requirements"]],
                ['sha' => 'ccc', 'html_url' => 'https://github.com/b13/http2/commit/ccc', 'commit' => ['message' => '[BUGFIX] rm foo header']],
                ['sha' => 'ddd', 'html_url' => 'https://github.com/b13/http2/commit/ddd', 'commit' => ['message' => "feat: new cache layer\n\nBREAKING CHANGE: the cache table is renamed"]],
                ['sha' => 'eee', 'html_url' => 'https://github.com/b13/http2/commit/eee', 'commit' => ['message' => "docs: explain the setup\n\nMentions a BREAKING CHANGE: of the core in passing"]],
            ] : []]);
        }, 'https://api.github.com/'));

        self::assertSame(2, $synchronizer->sync());
        self::assertSame(['ref-3...ref-4', 'ref-4...ref-41'], $compared, 'each version with its previous one, only above the installed 3.0.0');
        self::assertSame(0, $synchronizer->sync(), 'checked once');

        $byVersion = $this->breakingCommits();
        self::assertSame([
            ['sha' => 'aaa', 'subject' => '!!![TASK] change requirements', 'url' => 'https://github.com/b13/http2/commit/aaa'],
            ['sha' => 'ddd', 'subject' => 'feat: new cache layer', 'url' => 'https://github.com/b13/http2/commit/ddd', 'footer' => true],
        ], $byVersion['4.0.0'], 'no merge commit; the footer only on a line of its own');
        self::assertSame([], $byVersion['v4.1.0']);
        self::assertNull($byVersion['3.0.0'], 'not newer than the installed version');
    }

    public function testARateLimitLeavesTheRestUnchecked(): void
    {
        $synchronizer = $this->synchronizer(new MockHttpClient(new MockResponse('', ['http_code' => 403, 'response_headers' => ['x-ratelimit-remaining' => '0']]), 'https://api.github.com/'));

        self::assertSame(0, $synchronizer->sync());
        self::assertNull($this->breakingCommits()['4.0.0']);
    }

    public function testAPairGitHubCantCompareStaysUnknownButIsntAskedAgain(): void
    {
        $synchronizer = $this->synchronizer(new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['http_code' => 404]), 'https://api.github.com/'));

        self::assertSame(2, $synchronizer->sync());
        self::assertSame(0, $synchronizer->sync(), 'not asked again');
        self::assertNull($this->breakingCommits()['4.0.0'], 'unknown, not "nothing breaking"');
    }

    public function testWithoutATokenNothingIsRead(): void
    {
        self::assertNull($this->synchronizer(new MockHttpClient(), '')->sync());
    }

    private function synchronizer(MockHttpClient $http, string $token = 'secret-token'): BreakingCommitSynchronizer
    {
        $container = static::getContainer();

        return new BreakingCommitSynchronizer(
            new GitHubApiClient($http, 'https://observer.test', $token),
            $container->get(SitePackageRepository::class),
            $container->get(PackagistVersionRepository::class),
            $container->get(ExtensionVersionCatalog::class),
            $this->entityManager,
            new NullLogger(),
        );
    }

    /**
     * @return array<string, list<array{sha: string, subject: string, url: string}>|null>
     */
    private function breakingCommits(): array
    {
        $this->entityManager->clear();
        $byVersion = [];
        foreach (static::getContainer()->get(PackagistVersionRepository::class)->findByPackageName('b13/http2') as $version) {
            $byVersion[$version->getVersion()] = $version->getBreakingCommits();
        }

        return $byVersion;
    }
}
