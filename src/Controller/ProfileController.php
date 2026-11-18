<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
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

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository,
        TotpSecretEncryptor $encryptor,
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
                $form->get('currentPassword')->addError(new FormError('Current password is incorrect.'));
            } elseif (null !== $conflict && $conflict !== $user) {
                $form->get('email')->addError(new FormError('This email is already in use.'));
            } else {
                $user->setName($data['name']);
                $user->setEmail($data['email']);

                if (null !== $data['plainPassword'] && '' !== $data['plainPassword']) {
                    $user->setPassword($passwordHasher->hashPassword($user, $data['plainPassword']));
                }

                $entityManager->flush();
                $this->addFlash('success', 'Profile updated.');

                return $this->redirectToRoute('app_profile');
            }
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form,
            'twoFactorAvailable' => $encryptor->isConfigured(),
        ]);
    }
}
