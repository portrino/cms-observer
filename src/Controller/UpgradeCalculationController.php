<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\UpgradeSupportChart;
use App\Entity\Site;
use App\Entity\UpgradeCalculation;
use App\Entity\UpgradeCalculationVersion;
use App\Entity\UpgradeSection;
use App\Form\UpgradeCalculationVersionType;
use App\Repository\AgencySettingsRepository;
use App\Repository\UpgradeCalculationRepository;
use App\Service\UpgradeCalculationDrafter;
use App\Service\UpgradeCalculationRefresher;
use App\Service\UpgradeTotals;
use App\Service\UpgradeVersionComparison;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Major-upgrade calculations of a project. Saving always creates a new version (ADR-0004).
 */
class UpgradeCalculationController extends AbstractController
{
    public function __construct(
        private readonly UpgradeCalculationDrafter $drafter,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/projects/{id}/calculations', name: 'upgrade_calculation_index', methods: ['GET'])]
    public function index(Site $site, UpgradeCalculationRepository $repository): Response
    {
        $this->denyUnsupported($site);

        return $this->render('upgrade_calculation/index.html.twig', [
            'site' => $site,
            'calculations' => $repository->findBy(['site' => $site], ['createdAt' => 'DESC']),
            'targetMajors' => $this->drafter->targetMajors($site),
        ]);
    }

    #[Route('/projects/{id}/calculations/new', name: 'upgrade_calculation_new', methods: ['GET', 'POST'])]
    public function new(
        Site $site,
        Request $request,
        UpgradeCalculationRepository $repository,
        AgencySettingsRepository $settingsRepository,
        #[MapQueryParameter] int $target,
    ): Response {
        $this->denyUnsupported($site);
        if (!in_array($target, $this->drafter->targetMajors($site), true)) {
            throw $this->createNotFoundException('No upgrade to this major is possible.');
        }

        $previous = $repository->findOneBy(['site' => $site], ['createdAt' => 'DESC', 'id' => 'DESC'])?->getLatestVersion();
        $data = UpgradeCalculationVersionType::dataFrom($this->drafter->draft($site, $target), $previous, $settingsRepository->get());
        // the notes, e.g. the schedule, belong to the previous upgrade, not to this one
        $data['headerNotes'] = null;
        $form = $this->createForm(UpgradeCalculationVersionType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $calculation = new UpgradeCalculation($site, (int) $site->getCurrentCoreVersion(), $target);
            $this->entityManager->persist($calculation);

            return $this->saveVersion($calculation, $form);
        }

        return $this->render('upgrade_calculation/edit.html.twig', [
            'site' => $site,
            'title' => new TranslatableMessage('calculation.title.new', ['upgrade' => sprintf('%s %d → %d', $site->getType()->label(), (int) $site->getCurrentCoreVersion(), $target)]),
            'form' => $form,
            'cancelUrl' => $this->generateUrl('upgrade_calculation_index', ['id' => $site->getId()]),
        ]);
    }

    #[Route('/calculations/{id}', name: 'upgrade_calculation_show', methods: ['GET'])]
    public function show(UpgradeCalculation $calculation, #[MapQueryParameter] ?int $version = null): Response
    {
        $version = $this->findVersion($calculation, $version);
        $phases = $this->drafter->supportPhases($calculation);

        return $this->render('upgrade_calculation/show.html.twig', [
            'site' => $calculation->getSite(),
            'calculation' => $calculation,
            'version' => $version,
            'sections' => UpgradeSection::cases(),
            'totals' => new UpgradeTotals($version->getItems(), $version->getManagementPercent()),
            'supportPhases' => $phases,
            'supportChart' => [] !== $phases ? UpgradeSupportChart::of($phases, new \DateTimeImmutable()) : null,
        ]);
    }

    /** Two versions, by default the given or latest one and the one before it */
    #[Route('/calculations/{id}/compare', name: 'upgrade_calculation_compare', methods: ['GET'])]
    public function compare(UpgradeCalculation $calculation, #[MapQueryParameter] ?int $from = null, #[MapQueryParameter] ?int $to = null): Response
    {
        $new = $this->findVersion($calculation, $to);
        $versions = $calculation->getVersions();
        // newest first: the previous version follows the new one
        $previous = $versions[(int) array_search($new, $versions, true) + 1] ?? null;
        $old = null !== $from ? $this->findVersion($calculation, $from) : $previous;
        if (null === $old) {
            throw $this->createNotFoundException('The first version has nothing to compare with.');
        }

        return $this->render('upgrade_calculation/compare.html.twig', [
            'site' => $calculation->getSite(),
            'calculation' => $calculation,
            'comparison' => new UpgradeVersionComparison($old, $new),
            'sections' => UpgradeSection::cases(),
        ]);
    }

    #[Route('/calculations/{id}/edit', name: 'upgrade_calculation_edit', methods: ['GET', 'POST'])]
    public function edit(UpgradeCalculation $calculation, Request $request, #[MapQueryParameter] ?int $version = null): Response
    {
        $base = $this->findVersion($calculation, $version);

        return $this->versionForm($calculation, $base, UpgradeCalculationVersionType::dataFrom($base->getItems(), $base), $request, 'calculation.title.edit');
    }

    /**
     * The edit form with the package rows brought up to the latest report; nothing is saved until
     * someone saves it as a new version.
     */
    #[Route('/calculations/{id}/refresh', name: 'upgrade_calculation_refresh', methods: ['GET', 'POST'])]
    public function refresh(UpgradeCalculation $calculation, Request $request, UpgradeCalculationRefresher $refresher, TranslatorInterface $translator, #[MapQueryParameter] ?int $version = null): Response
    {
        $base = $this->findVersion($calculation, $version);
        $refreshed = $refresher->refresh($calculation, $base);
        $data = UpgradeCalculationVersionType::dataFrom($refreshed['items'], $base);
        $reportedAt = $calculation->getSite()->getLastSeenAt();
        // saved with the version, so in the language of whoever refreshes
        $data['note'] = null !== $reportedAt ? $translator->trans('calculation.refreshed_of', ['date' => $reportedAt]) : $translator->trans('calculation.refreshed');

        return $this->versionForm($calculation, $base, $data, $request, 'calculation.title.refresh', $refreshed['changes'], $refreshed['changedPackages']);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>|null    $changes         of a refresh, null when editing
     * @param list<string>         $changedPackages of a refresh: their rows are highlighted
     */
    private function versionForm(UpgradeCalculation $calculation, UpgradeCalculationVersion $base, array $data, Request $request, string $action, ?array $changes = null, array $changedPackages = []): Response
    {
        $form = $this->createForm(UpgradeCalculationVersionType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return $this->saveVersion($calculation, $form);
        }

        return $this->render('upgrade_calculation/edit.html.twig', [
            'site' => $calculation->getSite(),
            'title' => new TranslatableMessage($action, ['upgrade' => sprintf('%s %d → %d', $calculation->getSite()->getType()->label(), $calculation->getFromMajor(), $calculation->getTargetMajor())]),
            'based_on' => $base,
            'changes' => $changes,
            'changedPackages' => $changedPackages,
            'form' => $form,
            'cancelUrl' => $this->generateUrl('upgrade_calculation_show', ['id' => $calculation->getId(), 'version' => $base->getId()]),
        ]);
    }

    /**
     * @param FormInterface<array<string, mixed>> $form
     */
    private function saveVersion(UpgradeCalculation $calculation, FormInterface $form): Response
    {
        $data = $form->getData();
        $version = new UpgradeCalculationVersion(
            calculation: $calculation,
            author: trim((string) $data['author']),
            hourlyRate: (float) $data['hourlyRate'],
            managementPercent: (float) $data['managementPercent'],
            final: (bool) $data['final'],
            items: UpgradeCalculationVersionType::itemsFrom($data),
            note: $data['note'] ?? null,
            remarks: $data['remarks'] ?? null,
            headerNotes: $data['headerNotes'] ?? null,
        );
        $this->entityManager->persist($version);
        $this->entityManager->flush();

        $this->addFlash('success', 'calculation.saved');

        return $this->redirectToRoute('upgrade_calculation_show', ['id' => $calculation->getId()]);
    }

    /** The given version of this calculation, or its latest one */
    private function denyUnsupported(Site $site): void
    {
        if (!$this->drafter->supports($site)) {
            throw $this->createNotFoundException(sprintf('No upgrade calculations for %s projects yet.', $site->getType()->label()));
        }
    }

    private function findVersion(UpgradeCalculation $calculation, ?int $versionId): UpgradeCalculationVersion
    {
        foreach ($calculation->getVersions() as $version) {
            if (null === $versionId || $version->getId() === $versionId) {
                return $version;
            }
        }

        throw $this->createNotFoundException('No such version of this calculation.');
    }
}
