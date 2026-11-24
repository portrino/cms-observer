<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Entity\CmsType;
use App\Entity\Site;
use App\Entity\Typo3CoreRelease;
use App\Entity\Typo3ReleaseType;
use App\Entity\WordpressCoreRelease;
use App\Service\SiteCoreVersionEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SiteCoreVersionEvaluatorTest extends KernelTestCase
{
    public function testReportsTheMajorStatusOfEachSiteFromItsOwnCmsReleases(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        foreach (['13.4.20', '13.4.21', '14.0.0', '14.1.0'] as $version) {
            $entityManager->persist(new Typo3CoreRelease(
                version: $version,
                releaseDate: new \DateTimeImmutable(),
                type: Typo3ReleaseType::Regular,
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

    private function site(CmsType $type, ?string $coreVersion): Site
    {
        $site = new Site('Site '.$type->value.' '.$coreVersion, $type, 'https://'.$type->value.'-'.$coreVersion.'.test', 'hash-'.$type->value.'-'.$coreVersion);
        if (null !== $coreVersion) {
            $site->recordReport($coreVersion, '8.3.14', '10.11.19-MariaDB');
        }

        return $site;
    }
}
