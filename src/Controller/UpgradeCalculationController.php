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
use App\Service\UpgradeTotals;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

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
            'title' => sprintf('New calculation: Upgrade %s %d → %d', $site->getType()->label(), (int) $site->getCurrentCoreVersion(), $target),
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

    #[Route('/calculations/{id}/edit', name: 'upgrade_calculation_edit', methods: ['GET', 'POST'])]
    public function edit(UpgradeCalculation $calculation, Request $request, #[MapQueryParameter] ?int $version = null): Response
    {
        $base = $this->findVersion($calculation, $version);
        $form = $this->createForm(UpgradeCalculationVersionType::class, UpgradeCalculationVersionType::dataFrom($base->getItems(), $base));
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return $this->saveVersion($calculation, $form);
        }

        return $this->render('upgrade_calculation/edit.html.twig', [
            'site' => $calculation->getSite(),
            'title' => sprintf('Edit calculation: Upgrade %s %d → %d', $calculation->getSite()->getType()->label(), $calculation->getFromMajor(), $calculation->getTargetMajor()),
            'based_on' => $base,
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
            $calculation,
            trim((string) $data['author']),
            $data['note'] ?? null,
            (float) $data['hourlyRate'],
            (float) $data['managementPercent'],
            $data['remarks'] ?? null,
            (bool) $data['final'],
            UpgradeCalculationVersionType::itemsFrom($data),
            $data['headerNotes'] ?? null,
        );
        $this->entityManager->persist($version);
        $this->entityManager->flush();

        $this->addFlash('success', 'Saved as a new version.');

        return $this->redirectToRoute('upgrade_calculation_show', ['id' => $calculation->getId()]);
    }

    /** The given version of this calculation, or its latest one */
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
