<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Entity\CmsType;
use App\Entity\Site;
use App\Entity\Typo3\CoreRelease as Typo3CoreRelease;
use App\Entity\Typo3\ReleaseType;
use App\Entity\Wordpress\CoreRelease as WordpressCoreRelease;
use App\Service\SiteCoreVersionEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SiteCoreVersionEvaluatorTest extends KernelTestCase
{
    public function testReportsTheMajorStatusOfEachSiteFromItsOwnCmsReleases(): void
    {
        [$typo3, $wordpress, $unreported] = $this->sitesWithReleases();

        $status = static::getContainer()->get(SiteCoreVersionEvaluator::class)
            ->majorStatusBySite([$typo3, $wordpress, $unreported]);

        self::assertSame(
            [
                $typo3->getId() => [true, '13.4.21', '14.1.0'],
                $wordpress->getId() => [false, null, '7.0.0'],
                $unreported->getId() => [null, null, null],
            ],
            array_map(static fn (array $site): array => [$site['outdated'], $site['latestInMajor'], $site['newerMajor']?->getVersion()], $status),
            'each site is judged by the releases of its own CMS only',
        );
    }

    public function testListsTheNewerReleasesOfTheSitesOwnCms(): void
    {
        [$typo3, $wordpress] = $this->sitesWithReleases();
        $evaluator = static::getContainer()->get(SiteCoreVersionEvaluator::class);

        $typo3Releases = $evaluator->newerReleases($typo3);
        self::assertSame(['13.4.21'], array_map(static fn (Typo3CoreRelease|WordpressCoreRelease $release): string => $release->getVersion(), $typo3Releases['inMajor']));
        self::assertSame('14.1.0', $typo3Releases['newerMajor']?->getVersion());

        $wordpressReleases = $evaluator->newerReleases($wordpress);
        self::assertSame([], $wordpressReleases['inMajor']);
        self::assertSame('7.0.0', $wordpressReleases['newerMajor']?->getVersion());
    }

    /**
     * @return array{Site, Site, Site} a TYPO3 13.4.20, a WordPress 6.8.1 and a TYPO3 site without report
     */
    private function sitesWithReleases(): array
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        foreach (['13.4.20', '13.4.21', '14.0.0', '14.1.0'] as $version) {
            $entityManager->persist(new Typo3CoreRelease(
                version: $version,
                releaseDate: new \DateTimeImmutable(),
                type: ReleaseType::Regular,
                latestVersion: str_starts_with($version, '13.') ? '13.4.21' : '14.1.0',
                isActive: true,
                insecure: false,
                maintainedUntil: null,
            ));
        }
        foreach (['6.8.1', '7.0.0'] as $version) {
            $entityManager->persist(new WordpressCoreRelease($version, $version, false, null));
        }

        $typo3 = $this->site(CmsType::Typo3, '13.4.20');
        $wordpress = $this->site(CmsType::WordPress, '6.8.1');
        $unreported = $this->site(CmsType::Typo3, null);
        foreach ([$typo3, $wordpress, $unreported] as $site) {
            $entityManager->persist($site);
        }
        $entityManager->flush();

        return [$typo3, $wordpress, $unreported];
    }

    private function site(CmsType $type, ?string $coreVersion): Site
    {
        $site = new Site('Site '.$type->value.' '.$coreVersion, $type, 'https://'.$type->value.'-'.$coreVersion.'.test', 'hash-'.$type->value.'-'.$coreVersion);
        if (null !== $coreVersion) {
            $site->recordReport($coreVersion, '8.3.14', '10.11.19-MariaDB');
        }

        return $site;
    }
}
