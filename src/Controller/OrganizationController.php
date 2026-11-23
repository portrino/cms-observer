<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Organization;
use App\Form\OrganizationType;
use App\Repository\OrganizationRepository;
use App\Repository\SitePackageRepository;
use App\Repository\Typo3CoreReleaseRepository;
use App\Repository\WordpressCoreReleaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OrganizationController extends AbstractController
{
    #[Route('/organizations', name: 'dashboard_organization_index')]
    public function index(OrganizationRepository $organizationRepository): Response
    {
        return $this->render('dashboard/organization_index.html.twig', [
            'organizations' => $organizationRepository->findAllOrderedByName(),
        ]);
    }

    #[Route('/organizations/new', name: 'dashboard_organization_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(OrganizationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $organization = new Organization($data['name'], $data['description'], $data['website']);
            $entityManager->persist($organization);
            $entityManager->flush();

            $this->addFlash('success', 'organization.created');

            return $this->redirectToRoute('dashboard_organization_show', ['id' => $organization->getId()]);
        }

        return $this->render('dashboard/organization_new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/organizations/{id}', name: 'dashboard_organization_show')]
    public function show(
        Organization $organization,
        SitePackageRepository $packageRepository,
        Typo3CoreReleaseRepository $coreReleaseRepository,
        WordpressCoreReleaseRepository $wordpressCoreReleaseRepository,
    ): Response {
        return $this->render('dashboard/organization_show.html.twig', [
            'organization' => $organization,
            'packageStatusBySite' => $packageRepository->getAggregateStatusBySite(),
            'packageSummaryBySite' => $packageRepository->getPackageSummaryBySite($organization->getSites()),
            'coreMajorStatusBySite' => $coreReleaseRepository->getMajorStatusBySite($organization->getSites()) + $wordpressCoreReleaseRepository->getMajorStatusBySite($organization->getSites()),
        ]);
    }

    #[Route('/organizations/{id}/edit', name: 'dashboard_organization_edit')]
    public function edit(
        Organization $organization,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $form = $this->createForm(OrganizationType::class, [
            'name' => $organization->getName(),
            'description' => $organization->getDescription(),
            'website' => $organization->getWebsite(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $organization->updateDetails($data['name'], $data['description'], $data['website']);
            $entityManager->flush();

            $this->addFlash('success', 'organization.updated');

            return $this->redirectToRoute('dashboard_organization_show', ['id' => $organization->getId()]);
        }

        return $this->render('dashboard/organization_edit.html.twig', [
            'organization' => $organization,
            'form' => $form,
        ]);
    }
}
