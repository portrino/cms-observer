<?php

declare(strict_types=1);

namespace App\Service\Wordpress;

use App\Entity\PackageType;
use App\Entity\SitePackage;
use App\Entity\Wordpress\PluginVulnerability;
use App\Repository\Wordpress\PluginVulnerabilityRepository;
use App\Service\PackageUpdateResolver;
use App\Service\SecurityNotifier;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Resolves a WordPress plugin package's status. Callers are responsible for
 * only passing WordPress plugin packages (e.g. via
 * SitePackageRepository::findByPackageType(PackageType::WordpressPlugin)) -
 * see SitePackageTerEvaluator for TYPO3 extensions.
 *
 * Outdated comes from the synced wordpress.org versions (PackageUpdateResolver,
 * only versions installable on the site's WordPress), insecure from synced Wordfence vulnerabilities (Wordpress\PluginVulnerability),
 * since WordPress.org's plugin info API has no security field. Informational
 * vulnerabilities (no real-world impact) are ignored. Insecure stays null
 * (unknown) until the Wordfence feed was synced at least once.
 */
class SitePackageEvaluator
{
    private bool $vulnerabilityFeedSynced = false;

    public function __construct(
        private readonly PackageUpdateResolver $updateResolver,
        private readonly PluginVulnerabilityRepository $vulnerabilityRepository,
        private readonly SecurityNotifier $securityNotifier,
    ) {
    }

    public function evaluate(SitePackage $package): void
    {
        $wasInsecure = $package->isInsecure();

        $vulnerability = null;
        $insecure = null;
        if ($this->isVulnerabilityFeedSynced()) {
            $vulnerability = $this->findMostSevereVulnerability($package);
            $insecure = null !== $vulnerability;
        }

        $isTheme = PackageType::WordpressTheme === $package->getPackageType();
        $package->setVersionStatus($insecure, null, null);
        $coreVersion = $package->getSite()->getCurrentCoreVersion();
        $package->applyUpdate($isTheme
            ? $this->updateResolver->forWordpressTheme($package->getName(), $package->getVersion(), $coreVersion)
            : $this->updateResolver->forWordpressPlugin($package->getName(), $package->getVersion(), $coreVersion));
        $package->setMatchedWordpressVulnerability($vulnerability);

        if (null !== $vulnerability && true !== $wasInsecure) {
            $this->securityNotifier->addFinding(
                $package->getSite(),
                new TranslatableMessage($isTheme ? 'event.subject.theme' : 'event.subject.plugin', ['name' => $package->getName(), 'version' => $package->getVersion()]),
                new TranslatableMessage('event.detail.vulnerability', [
                    'id' => $vulnerability->getCve() ?? $vulnerability->getTitle(),
                    'severity' => $vulnerability->getCvssRating() ?? 'unknown',
                    'url' => $vulnerability->getUrl(),
                ]),
            );
        }
    }

    /**
     * @param iterable<SitePackage> $packages
     */
    public function evaluateAll(iterable $packages): void
    {
        foreach ($packages as $package) {
            $this->evaluate($package);
        }
    }

    private function findMostSevereVulnerability(SitePackage $package): ?PluginVulnerability
    {
        $matches = array_filter(
            $this->vulnerabilityRepository->findBySlug($package->getName(), $package->getPackageType()),
            static fn (PluginVulnerability $v): bool => !$v->isInformational() && $v->affects($package->getVersion()),
        );
        usort($matches, static fn (PluginVulnerability $a, PluginVulnerability $b): int => ($b->getCvssScore() ?? 0.0) <=> ($a->getCvssScore() ?? 0.0));

        return $matches[0] ?? null;
    }

    // once synced, the feed never goes away again, so only the "not yet" answer is re-checked
    private function isVulnerabilityFeedSynced(): bool
    {
        return $this->vulnerabilityFeedSynced = $this->vulnerabilityFeedSynced || $this->vulnerabilityRepository->hasAny();
    }
}
