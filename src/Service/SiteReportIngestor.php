<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ExtensionReport;
use App\Dto\SiteReportRequest;
use App\Entity\CmsType;
use App\Entity\Site;
use App\Entity\SiteEvent;
use App\Entity\SiteEventType;
use App\Entity\SitePackage;
use App\Entity\SiteReport;
use App\Repository\SitePackageRepository;
use App\Repository\SiteReportRepository;
use Doctrine\ORM\EntityManagerInterface;

class SiteReportIngestor
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SitePackageRepository $packageRepository,
        private readonly SiteReportRepository $reportRepository,
        private readonly SiteCoreVersionEvaluator $coreVersionEvaluator,
        private readonly SitePackageAdvisoryEvaluator $packageAdvisoryEvaluator,
        private readonly SitePackageWordpressEvaluator $wordpressEvaluator,
        private readonly SiteRuntimeVersionEvaluator $runtimeVersionEvaluator,
        private readonly SecurityNotifier $securityNotifier,
    ) {
    }

    /**
     * Records a report from an instance whose domains don't match the site (e.g. a staging copy
     * with the production token) without touching the site's data, so the dashboard can show it.
     *
     * @param list<string> $domains
     */
    public function reject(Site $site, array $domains): void
    {
        $site->recordRejectedReport($domains);
        $this->entityManager->persist(new SiteEvent($site, SiteEventType::ReportRejected, detail: implode(', ', $domains)));
        $this->entityManager->flush();
    }

    public function ingest(Site $site, SiteReportRequest $request): SiteReport
    {
        $payload = [
            'coreVersion' => $request->coreVersion,
            'phpVersion' => $request->phpVersion,
            'dbVersion' => $request->dbVersion,
            'clientVersion' => $request->clientVersion,
            'applicationContext' => $request->applicationContext,
            'domains' => $request->domains,
            'dbPlatform' => $request->dbPlatform,
            'extensions' => array_map(
                static fn (ExtensionReport $extension): array => [
                    'name' => $extension->name,
                    'title' => $extension->title,
                    'version' => $extension->version,
                    'active' => $extension->active,
                    'composerName' => $extension->composerName,
                ],
                $request->extensions ?? [],
            ),
        ];

        // The raw dbVersion (up to ~40 chars on TYPO3 12) is only kept in the payload
        [$dbPlatform, $dbVersion] = DbVersionNormalizer::normalize($request->dbPlatform, $request->dbVersion);

        // Compare against the stored state before recordReport() overwrites it
        $isFirstReport = null === $site->getLastSeenAt();
        if ($isFirstReport) {
            $this->entityManager->persist(new SiteEvent(
                $site,
                SiteEventType::FirstReport,
                null,
                null,
                sprintf('%s %s', $site->getType()->label(), $request->coreVersion),
                sprintf('%d packages', count($request->extensions ?? [])),
            ));
        } else {
            $this->recordRuntimeChanges($site, $request, $dbPlatform.' '.$dbVersion);
        }

        $report = new SiteReport($site, $request->coreVersion, $request->phpVersion, $dbVersion, $payload);
        $this->entityManager->persist($report);

        $site->recordReport($request->coreVersion, $request->phpVersion, $dbVersion, $dbPlatform);
        $this->coreVersionEvaluator->evaluate($site);
        $this->runtimeVersionEvaluator->evaluate($site);

        // A payload without "extensions" says nothing about the packages, an empty list means none are installed
        if (null !== $request->extensions) {
            $this->syncPackages($site, $request->extensions, !$isFirstReport);
        }

        $this->entityManager->flush();
        $this->securityNotifier->flush();

        return $report;
    }

    private function recordRuntimeChanges(Site $site, SiteReportRequest $request, string $db): void
    {
        $previousReport = $this->reportRepository->findOneBy(['site' => $site], ['receivedAt' => 'DESC', 'id' => 'DESC']);
        $previousClientVersion = $previousReport?->getPayload()['clientVersion'] ?? null;

        $this->recordChange($site, SiteEventType::CoreChanged, null, $site->getCurrentCoreVersion(), $request->coreVersion);
        $this->recordChange($site, SiteEventType::PhpChanged, null, $site->getCurrentPhpVersion(), $request->phpVersion);
        $this->recordChange($site, SiteEventType::DbChanged, null, trim($site->getCurrentDbPlatform().' '.$site->getCurrentDbVersion()), $db);
        $this->recordChange($site, SiteEventType::ClientChanged, null, is_string($previousClientVersion) ? $previousClientVersion : null, $request->clientVersion);
    }

    /**
     * @param ExtensionReport[] $extensions
     */
    private function syncPackages(Site $site, array $extensions, bool $recordEvents): void
    {
        $remaining = [];
        foreach ($this->packageRepository->findBy(['site' => $site]) as $package) {
            $remaining[$package->getName()] = $package;
        }

        foreach ($extensions as $extension) {
            $sitePackage = $remaining[$extension->name] ?? null;
            unset($remaining[$extension->name]);

            if (null !== $sitePackage) {
                if ($recordEvents) {
                    $this->recordPackageChanges($sitePackage, $extension);
                }
                $sitePackage->update($extension->title, $extension->version, $extension->active, $extension->composerName);
            } else {
                $sitePackage = new SitePackage($site, $extension->name, $extension->title, $extension->version, $extension->active, $extension->composerName);
                $this->entityManager->persist($sitePackage);
                if ($recordEvents) {
                    $this->entityManager->persist(new SiteEvent($site, SiteEventType::PackageInstalled, $extension->name, null, $extension->version));
                }
            }

            // WordPress plugins are checked against the synced Wordfence data right away
            if (CmsType::WordPress === $site->getType()) {
                $this->wordpressEvaluator->evaluate($sitePackage);
            } else {
                $this->packageAdvisoryEvaluator->evaluate($sitePackage);
            }
        }

        // No longer reported means uninstalled; keeping it would keep alerting about software that's gone
        foreach ($remaining as $removed) {
            $this->entityManager->persist(new SiteEvent($site, SiteEventType::PackageRemoved, $removed->getName(), $removed->getVersion()));
            $this->entityManager->remove($removed);
        }
    }

    private function recordPackageChanges(SitePackage $package, ExtensionReport $extension): void
    {
        $site = $package->getSite();
        $this->recordChange($site, SiteEventType::PackageUpdated, $package->getName(), $package->getVersion(), $extension->version);
        if ($package->isActive() !== $extension->active) {
            $type = $extension->active ? SiteEventType::PackageActivated : SiteEventType::PackageDeactivated;
            $this->entityManager->persist(new SiteEvent($site, $type, $package->getName(), null, $extension->version));
        }
    }

    private function recordChange(Site $site, SiteEventType $type, ?string $subject, ?string $old, ?string $new): void
    {
        if (null !== $new && $old !== $new) {
            $this->entityManager->persist(new SiteEvent($site, $type, $subject, $old, $new));
        }
    }
}
