<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CmsType;
use App\Entity\SitePackage;
use App\Service\Craft\SitePackageEvaluator as CraftSitePackageEvaluator;
use App\Service\Typo3\SitePackageAdvisoryEvaluator;
use App\Service\Wordpress\SitePackageEvaluator as WordpressSitePackageEvaluator;

/**
 * Rates a package right when a report brings it, against the data synced for its CMS: the
 * advisories (TYPO3, Craft CMS) or the Wordfence vulnerabilities (WordPress). The TER versions
 * of TYPO3 extensions are only rated by their sync.
 */
class ReportedPackageEvaluator
{
    public function __construct(
        private readonly SitePackageAdvisoryEvaluator $typo3Evaluator,
        private readonly WordpressSitePackageEvaluator $wordpressEvaluator,
        private readonly CraftSitePackageEvaluator $craftEvaluator,
    ) {
    }

    public function evaluate(SitePackage $package): void
    {
        match ($package->getSite()->getType()) {
            CmsType::Typo3 => $this->typo3Evaluator->evaluate($package),
            CmsType::WordPress => $this->wordpressEvaluator->evaluate($package),
            CmsType::Craft => $this->craftEvaluator->evaluate($package),
        };
    }
}
