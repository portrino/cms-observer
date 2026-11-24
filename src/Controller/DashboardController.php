<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ProjectListQuery;
use App\Dto\ProjectSecurityFilter;
use App\Dto\ProjectSlaFilter;
use App\Dto\SiteHistoryQuery;
use App\Entity\CmsType;
use App\Entity\Organization;
use App\Entity\PackageType;
use App\Entity\Site;
use App\Entity\SiteEventType;
use App\Form\SiteType;
use App\Repository\EndOfLifeReleaseRepository;
use App\Repository\OrganizationRepository;
use App\Repository\SiteEventRepository;
use App\Repository\SitePackageRepository;
use App\Repository\SiteReportRepository;
use App\Repository\SiteRepository;
use App\Repository\Typo3CoreReleaseRepository;
use App\Repository\WordpressCoreReleaseRepository;
use App\Security\SiteTokenFactory;
use App\Service\SiteCoreVersionEvaluator;
use App\Service\SitePackageDetailsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\TranslatableMessage;

class DashboardController extends AbstractController
{
    private const int HISTORY_PER_PAGE = 50;

    #[Route('/', name: 'dashboard_index')]
    public function index(
        SiteRepository $siteRepository,
        SitePackageRepository $packageRepository,
        SiteCoreVersionEvaluator $coreVersionEvaluator,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_NOT_FOUND)] ProjectListQuery $query = new ProjectListQuery(),
    ): Response {
        $search = null !== $query->q && '' !== trim($query->q) ? trim($query->q) : null;
        $sites = $siteRepository->search($search, $query->type, null !== $query->sla ? ProjectSlaFilter::With === $query->sla : null);
        $packageStatusBySite = $packageRepository->getAggregateStatusBySite();

        // the security state is aggregated across tables, filtering the loaded sites is enough for a few hundred
        if (null !== $query->security) {
            $sites = array_values(array_filter(
                $sites,
                static fn (Site $site): bool => $query->security->matches($site, $packageStatusBySite[$site->getId()]['insecure'] ?? null),
            ));
        }

        return $this->render('dashboard/index.html.twig', [
            'sites' => $sites,
            'query' => $search,
            'filter' => $query,
            'cmsTypes' => CmsType::cases(),
            'securityFilters' => ProjectSecurityFilter::cases(),
            'slaFilters' => ProjectSlaFilter::cases(),
            'packageStatusBySite' => $packageStatusBySite,
            'packageSummaryBySite' => $packageRepository->getPackageSummaryBySite($sites),
            'coreMajorStatusBySite' => $coreVersionEvaluator->majorStatusBySite($sites),
        ]);
    }

    #[Route('/projects/new', name: 'dashboard_site_new')]
    public function newSite(Request $request, EntityManagerInterface $entityManager, SiteTokenFactory $tokenFactory, OrganizationRepository $organizationRepository): Response
    {
        $form = $this->createForm(SiteType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData() ?? throw new \LogicException('A submitted form without data_class has array data.');

            [$token, $tokenHash] = $tokenFactory->generate();

            $site = new Site(
                $data['name'],
                $data['type'],
                $data['url'],
                $tokenHash,
                $data['slaActive'],
                $this->chosenOrganization($data, $organizationRepository),
                $data['repositoryUrl'],
                $data['issueTrackerUrl'],
            );
            $entityManager->persist($site);
            $entityManager->flush();

            $this->addFlash('site_token', $token);

            return $this->redirectToRoute('dashboard_site_show', ['id' => $site->getId()]);
        }

        return $this->render('dashboard/site_new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/projects/{id}', name: 'dashboard_site_show')]
    public function show(
        Site $site,
        SitePackageRepository $packageRepository,
        Typo3CoreReleaseRepository $coreReleaseRepository,
        WordpressCoreReleaseRepository $wordpressCoreReleaseRepository,
        EndOfLifeReleaseRepository $endOfLifeReleaseRepository,
        SiteReportRepository $reportRepository,
        SitePackageDetailsProvider $packageDetailsProvider,
    ): Response {
        $packages = $packageRepository->findBy(['site' => $site], ['name' => 'ASC']);
        $coreVersion = $site->getCurrentCoreVersion();
        $isWordpress = CmsType::WordPress === $site->getType();

        // TYPO3 major => support status, to color the compatibility badges of TER versions
        $typo3SupportStatus = [];
        if (!$isWordpress) {
            foreach ($endOfLifeReleaseRepository->findBy(['product' => 'typo3']) as $release) {
                $typo3SupportStatus[(int) $release->getCycle()] = $release->getSupportStatus();
            }
        }

        return $this->render('dashboard/site_show.html.twig', [
            'site' => $site,
            'lastReport' => $reportRepository->findOneBy(['site' => $site], ['receivedAt' => 'DESC']),
            'typo3SupportStatus' => $typo3SupportStatus,
            'packages' => $packages,
            'packageDetails' => $packageDetailsProvider->forSite($site, $packages),
            'newerCoreReleases' => $isWordpress
                ? $wordpressCoreReleaseRepository->findNewerThan($coreVersion)
                : $coreReleaseRepository->findNewerThan($coreVersion),
            'newerMajorCoreRelease' => $isWordpress
                ? $wordpressCoreReleaseRepository->findLatestInNewerMajor($coreVersion)
                : $coreReleaseRepository->findLatestInNewerMajor($coreVersion),
        ]);
    }

    #[Route('/projects/{id}/history', name: 'dashboard_site_history')]
    public function history(
        Site $site,
        SiteEventRepository $eventRepository,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_NOT_FOUND)] SiteHistoryQuery $query = new SiteHistoryQuery(),
    ): Response {
        $search = null !== $query->q && '' !== trim($query->q) ? trim($query->q) : null;
        $events = $eventRepository->search($site, $search, $query->type, $query->page, self::HISTORY_PER_PAGE);

        return $this->render('dashboard/site_history.html.twig', [
            'site' => $site,
            'events' => $events,
            'query' => $search,
            'type' => $query->type,
            'eventTypes' => SiteEventType::cases(),
            'page' => $query->page,
            'pageCount' => max(1, (int) ceil(count($events) / self::HISTORY_PER_PAGE)),
        ]);
    }

    #[Route('/projects/{id}/edit', name: 'dashboard_site_edit')]
    public function editSite(
        Site $site,
        Request $request,
        EntityManagerInterface $entityManager,
        OrganizationRepository $organizationRepository,
    ): Response {
        $form = $this->createForm(SiteType::class, [
            'name' => $site->getName(),
            'type' => $site->getType(),
            'url' => $site->getUrl(),
            'slaActive' => $site->isSlaActive(),
            'organization' => $site->getOrganization(),
            'repositoryUrl' => $site->getRepositoryUrl(),
            'issueTrackerUrl' => $site->getIssueTrackerUrl(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $site->updateDetails(
                $data['name'],
                $data['type'],
                $data['url'],
                $data['slaActive'],
                $this->chosenOrganization($data, $organizationRepository),
                $data['repositoryUrl'],
                $data['issueTrackerUrl'],
            );
            $entityManager->flush();

            $this->addFlash('success', 'project.updated');

            return $this->redirectToRoute('dashboard_site_show', ['id' => $site->getId()]);
        }

        return $this->render('dashboard/site_edit.html.twig', [
            'site' => $site,
            'form' => $form,
        ]);
    }

    #[Route('/projects/{id}/regenerate-token', name: 'dashboard_site_regenerate_token', methods: ['POST'])]
    public function regenerateToken(
        Site $site,
        Request $request,
        EntityManagerInterface $entityManager,
        SiteTokenFactory $tokenFactory,
    ): Response {
        if (!$this->isCsrfTokenValid('regenerate-token-'.$site->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        [$token, $tokenHash] = $tokenFactory->generate();
        $site->replaceTokenHash($tokenHash);
        $entityManager->flush();

        $this->addFlash('site_token', $token);

        return $this->redirectToRoute('dashboard_site_show', ['id' => $site->getId()]);
    }

    #[Route('/projects/{id}/delete', name: 'dashboard_site_delete', methods: ['POST'])]
    public function deleteSite(
        Site $site,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('delete-site-'.$site->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $expectedConfirmation = null !== $site->getOrganization()
            ? $site->getOrganization()->getName().'/'.$site->getName()
            : $site->getName();

        if (trim($request->request->getString('confirmation')) !== $expectedConfirmation) {
            $this->addFlash('error', 'project.delete_mismatch');

            return $this->redirectToRoute('dashboard_site_edit', ['id' => $site->getId()]);
        }

        $entityManager->remove($site);
        $entityManager->flush();

        $this->addFlash('success', new TranslatableMessage('project.deleted', ['name' => $expectedConfirmation]));

        return $this->redirectToRoute('dashboard_index');
    }

    #[Route('/packages', name: 'dashboard_packages')]
    public function packages(Request $request, SitePackageRepository $packageRepository): Response
    {
        $query = $request->query->get('q');
        $query = is_string($query) && '' !== $query ? $query : null;

        $packageType = PackageType::tryFrom((string) $request->query->get('type'));

        // Grouped by package name for the accordion view - search() already
        // orders by name first, so installs of the same package stay contiguous.
        $groups = [];
        foreach ($packageRepository->search($query, $packageType) as $package) {
            // a theme may share its slug with a plugin
            $groups[$package->getName().(PackageType::WordpressTheme === $package->getPackageType() ? ' (theme)' : '')][] = $package;
        }

        return $this->render('dashboard/packages.html.twig', [
            'packageGroups' => $groups,
            'query' => $query,
            'packageType' => $packageType,
            'packageTypes' => PackageType::cases(),
        ]);
    }

    /**
     * The selected organization, or the one entered as new (SiteType rejects both at once).
     *
     * @param array<string, mixed> $data
     */
    private function chosenOrganization(array $data, OrganizationRepository $organizationRepository): ?Organization
    {
        if ($data['organization'] instanceof Organization) {
            return $data['organization'];
        }
        $newName = trim((string) ($data['newOrganization'] ?? ''));

        return '' === $newName ? null : $organizationRepository->findOrCreateByName($newName);
    }
}
