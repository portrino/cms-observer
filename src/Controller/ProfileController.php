<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\LanguageType;
use App\Form\ProfileType;
use App\Repository\UserRepository;
use App\Security\TotpSecretEncryptor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository,
        TotpSecretEncryptor $encryptor,
        TranslatorInterface $translator,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProfileType::class, ['name' => $user->getName(), 'email' => $user->getEmail()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $currentPassword = (string) $form->get('currentPassword')->getData();
            $conflict = $userRepository->findOneByEmail($data['email']);

            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $form->get('currentPassword')->addError(new FormError($translator->trans('profile.error.password')));
            } elseif (null !== $conflict && $conflict !== $user) {
                $form->get('email')->addError(new FormError($translator->trans('profile.error.email')));
            } else {
                $user->setName($data['name']);
                $user->setEmail($data['email']);

                if (null !== $data['plainPassword'] && '' !== $data['plainPassword']) {
                    $user->setPassword($passwordHasher->hashPassword($user, $data['plainPassword']));
                }

                $entityManager->flush();
                $this->addFlash('success', 'profile.updated');

                return $this->redirectToRoute('app_profile');
            }
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form,
            'languageForm' => $this->createForm(LanguageType::class, ['locale' => $user->getLocale() ?? ''], ['action' => $this->generateUrl('app_profile_language')]),
            'twoFactorAvailable' => $encryptor->isConfigured(),
        ]);
    }

    #[Route('/profile/language', name: 'app_profile_language', methods: ['POST'])]
    public function language(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(LanguageType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $locale = $form->getData()['locale'] ?? null;
            $user->setLocale(null !== $locale && '' !== $locale ? $locale : null);
            $entityManager->flush();
            $this->addFlash('success', 'profile.language_saved');
        }

        return $this->redirectToRoute('app_profile');
    }
}
