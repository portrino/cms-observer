<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\PackageReport;
use App\Dto\SiteReportRequest;
use App\Entity\PackageType;
use App\Entity\Site;
use App\Entity\SiteEvent;
use App\Entity\SiteEventType;
use App\Entity\SitePackage;
use App\Entity\SiteReport;
use App\Repository\SitePackageRepository;
use App\Repository\SiteReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Translation\TranslatableMessage;

class SiteReportIngestor
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SitePackageRepository $packageRepository,
        private readonly SiteReportRepository $reportRepository,
        private readonly SiteCoreVersionEvaluator $coreVersionEvaluator,
        private readonly ReportedPackageEvaluator $packageEvaluator,
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
            'packages' => null !== $request->packages ? array_map($this->packagePayload(...), $request->packages) : null,
            'extensions' => null !== $request->extensions ? array_map($this->packagePayload(...), $request->extensions) : null,
        ];

        // The raw dbVersion (up to ~40 chars on TYPO3 12) is only kept in the payload
        [$dbPlatform, $dbVersion] = DbVersionNormalizer::normalize($request->dbPlatform, $request->dbVersion);
        // likewise the raw PHP version: a build suffix ("8.3.37-nmm1") would sort before the plain release
        $phpVersion = 1 === preg_match('/^\d+(?:\.\d+)*/', trim($request->phpVersion), $matches) ? $matches[0] : $request->phpVersion;

        // Compare against the stored state before recordReport() overwrites it
        $isFirstReport = null === $site->getLastSeenAt();
        if ($isFirstReport) {
            // current clients send packages, older ones extensions
            $packageCount = count($request->packages ?? $request->extensions ?? []);
            $this->entityManager->persist(new SiteEvent(
                $site,
                SiteEventType::FirstReport,
                null,
                null,
                sprintf('%s %s', $site->getType()->label(), $request->coreVersion),
                sprintf('%d packages', $packageCount),
                SiteEvent::messagesOf(null, new TranslatableMessage('event.detail.packages', ['count' => $packageCount])),
            ));
        } else {
            $this->recordRuntimeChanges($site, $request, $phpVersion, $dbPlatform.' '.$dbVersion);
        }

        $report = new SiteReport($site, $request->coreVersion, $phpVersion, $dbVersion, $payload);
        $this->entityManager->persist($report);

        $site->recordReport($request->coreVersion, $phpVersion, $dbVersion, $dbPlatform);
        $this->evaluateApplicationContext($site, $request->applicationContext);
        $this->coreVersionEvaluator->evaluate($site);
        $this->runtimeVersionEvaluator->evaluate($site);

        $this->syncReportedPackages($site, $request, !$isFirstReport);

        $this->entityManager->flush();
        $this->securityNotifier->flush();

        return $report;
    }

    private function syncReportedPackages(Site $site, SiteReportRequest $request, bool $recordEvents): void
    {
        // A payload without packages says nothing about them, an empty list means none are installed
        if (null !== $request->packages) {
            $byType = $this->packagesByType($site, $request->packages);
            // complete for every type of the CMS: a type without entries has none installed
            foreach (PackageType::allOf($site->getType()) as $type) {
                $this->syncPackages($site, $byType[$type->value] ?? [], $type, $recordEvents);
            }
        } elseif (null !== $request->extensions) {
            // older clients: only the CMS's default type, e.g. WordPress plugins without the themes
            $this->syncPackages($site, $request->extensions, PackageType::fromCmsType($site->getType()), $recordEvents);
        }
    }

    /**
     * @param PackageReport[] $packages
     *
     * @return array<string, list<PackageReport>> keyed by package type
     */
    private function packagesByType(Site $site, array $packages): array
    {
        $byType = [];
        foreach ($packages as $reported) {
            $type = PackageType::fromReport($site->getType(), $reported->type);
            if (null !== $type) { // an unknown type, e.g. from a newer client, is left out
                $byType[$type->value][] = $reported;
            }
        }

        return $byType;
    }

    private function evaluateApplicationContext(Site $site, ?string $context): void
    {
        $wasOutside = $site->runsOutsideProductionContext();
        $site->recordApplicationContext($context);

        if ($site->runsOutsideProductionContext() && !$wasOutside) {
            $this->securityNotifier->addFinding($site, new TranslatableMessage('event.subject.context', ['context' => $context]), new TranslatableMessage('event.detail.context_not_production'));
        } elseif ($wasOutside && null !== $context && !$site->runsOutsideProductionContext()) {
            $this->securityNotifier->recordResolved($site, new TranslatableMessage('event.subject.context', ['context' => $context]), new TranslatableMessage('event.detail.context_production'));
        }
    }

    private function recordRuntimeChanges(Site $site, SiteReportRequest $request, string $phpVersion, string $db): void
    {
        $previousReport = $this->reportRepository->findOneBy(['site' => $site], ['receivedAt' => 'DESC', 'id' => 'DESC']);
        $previousClientVersion = $previousReport?->getPayload()['clientVersion'] ?? null;

        $this->recordChange($site, SiteEventType::CoreChanged, null, $site->getCurrentCoreVersion(), $request->coreVersion);
        $this->recordChange($site, SiteEventType::PhpChanged, null, $site->getCurrentPhpVersion(), $phpVersion);
        $this->recordChange($site, SiteEventType::DbChanged, null, trim($site->getCurrentDbPlatform().' '.$site->getCurrentDbVersion()), $db);
        $this->recordChange($site, SiteEventType::ClientChanged, null, is_string($previousClientVersion) ? $previousClientVersion : null, $request->clientVersion);
    }

    /**
     * @param PackageReport[] $reportedPackages
     */
    private function syncPackages(Site $site, array $reportedPackages, PackageType $packageType, bool $recordEvents): void
    {
        $remaining = [];
        // per type: the list of one type says nothing about the packages of another
        foreach ($this->packageRepository->findBy(['site' => $site, 'packageType' => $packageType]) as $package) {
            $remaining[$package->getName()] = $package;
        }

        foreach ($reportedPackages as $reported) {
            $sitePackage = $this->savePackage($site, $remaining[$reported->name] ?? null, $reported, $packageType, $recordEvents);
            unset($remaining[$reported->name]);

            $this->packageEvaluator->evaluate($sitePackage);
        }

        // No longer reported means uninstalled; keeping it would keep alerting about software that's gone
        foreach ($remaining as $removed) {
            $this->entityManager->persist(new SiteEvent($site, SiteEventType::PackageRemoved, $this->eventSubject($removed), $removed->getVersion()));
            $this->entityManager->remove($removed);
        }
    }

    /**
     * The known package updated to the report, or the reported one added.
     */
    private function savePackage(Site $site, ?SitePackage $known, PackageReport $reported, PackageType $packageType, bool $recordEvents): SitePackage
    {
        $version = PackageVersion::normalize($reported->version);

        if (null !== $known) {
            if ($recordEvents) {
                $this->recordPackageChanges($known, $reported, $version);
            }
            $known->update($reported->displayTitle(), $version, $reported->active, $reported->composerName);

            return $known;
        }

        $sitePackage = new SitePackage($site, $reported->name, $reported->displayTitle(), $version, $reported->active, $reported->composerName, $packageType);
        $this->entityManager->persist($sitePackage);
        if ($recordEvents) {
            $this->entityManager->persist(new SiteEvent($site, SiteEventType::PackageInstalled, $this->eventSubject($sitePackage), null, $version));
        }

        return $sitePackage;
    }

    private function recordPackageChanges(SitePackage $package, PackageReport $reported, string $version): void
    {
        $site = $package->getSite();
        $this->recordChange($site, SiteEventType::PackageUpdated, $this->eventSubject($package), $package->getVersion(), $version);
        if ($package->isActive() !== $reported->active) {
            $type = $reported->active ? SiteEventType::PackageActivated : SiteEventType::PackageDeactivated;
            $this->entityManager->persist(new SiteEvent($site, $type, $this->eventSubject($package), null, $version));
        }
    }

    /** A theme may share its slug with a plugin, the history tells them apart */
    private function eventSubject(SitePackage $package): string
    {
        return PackageType::WordpressTheme === $package->getPackageType() ? $package->getName().' (theme)' : $package->getName();
    }

    /**
     * @return array<string, mixed>
     */
    private function packagePayload(PackageReport $reported): array
    {
        return [
            'name' => $reported->name,
            'title' => $reported->title,
            'version' => $reported->version,
            'active' => $reported->active,
            'composerName' => $reported->composerName,
            'type' => $reported->type,
        ];
    }

    private function recordChange(Site $site, SiteEventType $type, ?string $subject, ?string $old, ?string $new): void
    {
        if (null !== $new && $old !== $new) {
            $this->entityManager->persist(new SiteEvent($site, $type, $subject, $old, $new));
        }
    }
}
