<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\AgencySettingsType;
use App\Repository\AgencySettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Settings of the agency, as opposed to the personal profile.
 */
class SettingsController extends AbstractController
{
    #[Route('/settings', name: 'app_settings')]
    public function edit(Request $request, AgencySettingsRepository $repository, EntityManagerInterface $entityManager): Response
    {
        $settings = $repository->get();
        $form = $this->createForm(AgencySettingsType::class, [
            'defaultHourlyRate' => $settings->getDefaultHourlyRate(),
            'defaultManagementPercent' => $settings->getDefaultManagementPercent(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $settings->setUpgradeCalculationDefaults(
                null !== $data['defaultHourlyRate'] ? (float) $data['defaultHourlyRate'] : null,
                null !== $data['defaultManagementPercent'] ? (float) $data['defaultManagementPercent'] : null,
            );
            $entityManager->persist($settings);
            $entityManager->flush();
            $this->addFlash('success', 'settings.saved');

            return $this->redirectToRoute('app_settings');
        }

        return $this->render('settings/edit.html.twig', ['form' => $form]);
    }
}
