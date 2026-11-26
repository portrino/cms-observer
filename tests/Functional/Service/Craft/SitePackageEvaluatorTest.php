<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service\Craft;

use App\Entity\CmsType;
use App\Entity\Craft\Advisory;
use App\Entity\Craft\PluginVersion;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Service\Craft\SitePackageEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SitePackageEvaluatorTest extends KernelTestCase
{
    public function testRatesAPluginByItsAdvisoriesAndTheVersionsForTheSitesCraft(): void
    {
        $package = $this->package('3.4.10', 'nystudio107/craft-seomatic');

        static::getContainer()->get(SitePackageEvaluator::class)->evaluate($package);

        self::assertTrue($package->isInsecure());
        self::assertSame('PKSA-seo', $package->getMatchedCraftAdvisory()?->getAdvisoryId());
        self::assertTrue($package->isOutdated());
        self::assertSame('5.1.24', $package->getLatestVersion(), 'the newest version for Craft 5');
        self::assertSame('6', $package->getNewerCoreVersion());
        self::assertSame('6.0.0', $package->getNewerCorePackageVersion());
    }

    public function testTheLatestVersionIsSecureAndUpToDate(): void
    {
        $package = $this->package('5.1.24', 'nystudio107/craft-seomatic');

        static::getContainer()->get(SitePackageEvaluator::class)->evaluate($package);

        self::assertFalse($package->isInsecure());
        self::assertFalse($package->isOutdated());
    }

    public function testABranchOrAPluginWithoutComposerNameStaysUnknown(): void
    {
        $evaluator = static::getContainer()->get(SitePackageEvaluator::class);
        $branch = $this->package('dev-main', 'nystudio107/craft-seomatic');
        $private = $this->package('1.0.0', null);

        $evaluator->evaluateAll([$branch, $private]);

        self::assertNull($branch->isInsecure());
        self::assertNull($branch->isOutdated());
        self::assertNull($private->isInsecure());
        self::assertNull($private->isOutdated());
    }

    private function package(string $version, ?string $composerName): SitePackage
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $site = new Site('Craft '.$version, CmsType::Craft, 'https://craft.test', 'hash-'.$version.$composerName);
        $site->recordReport('5.12.4', '8.4.1', '10.11.9');
        $entityManager->persist($site);
        if ([] === $entityManager->getRepository(PluginVersion::class)->findAll()) {
            $entityManager->persist(new PluginVersion('nystudio107/craft-seomatic', '3.4.11', null, '^3.0.0'));
            $entityManager->persist(new PluginVersion('nystudio107/craft-seomatic', '5.1.23', null, '^5.0.0'));
            $entityManager->persist(new PluginVersion('nystudio107/craft-seomatic', '5.1.24', null, '^5.0.0'));
            $entityManager->persist(new PluginVersion('nystudio107/craft-seomatic', '6.0.0', null, '^6.0.0'));
            $entityManager->persist(new Advisory('PKSA-seo', 'nystudio107/craft-seomatic', 'Code Injection in SEOmatic', 'https://github.com/advisories/GHSA-g7xr-v82w-qggq', '<3.4.11', 'CVE-2021-41749', 'critical'));
        }
        $package = new SitePackage($site, 'seomatic', 'SEOmatic', $version, true, $composerName);
        $entityManager->persist($package);
        $entityManager->flush();

        return $package;
    }
}
