<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\TwoFactorConfirmationType;
use App\Security\BackupCodeGenerator;
use App\Security\PendingTotpSetup;
use App\Security\TotpSecretEncryptor;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Optional TOTP 2FA on the profile page: set up, show fresh backup codes
 * once, regenerate them, disable. The login side is the scheb/2fa bundle.
 */
#[Route('/profile/2fa')]
class TwoFactorController extends AbstractController
{
    // the secret during setup stays server-side until the first code confirms it
    private const string SETUP_SECRET = 'two_factor_setup_secret';
    private const string NEW_BACKUP_CODES = 'two_factor_new_backup_codes';

    public function __construct(
        private readonly TotpAuthenticatorInterface $totpAuthenticator,
        private readonly TotpSecretEncryptor $encryptor,
        private readonly BackupCodeGenerator $backupCodeGenerator,
        private readonly EntityManagerInterface $entityManager,
        #[Target('two_factor_profile')]
        private readonly RateLimiterFactoryInterface $codeCheckLimiter,
    ) {
    }

    #[Route('/enable', name: 'app_2fa_enable')]
    public function enable(Request $request): Response
    {
        $user = $this->getAppUser();
        if ($user->isTotpAuthenticationEnabled()) {
            return $this->redirectToRoute('app_profile');
        }
        // the profile page explains it, see twoFactorAvailable there
        if (!$this->encryptor->isConfigured()) {
            return $this->redirectToRoute('app_profile');
        }

        $session = $request->getSession();
        $secret = $session->get(self::SETUP_SECRET);
        if (!is_string($secret)) {
            $secret = $this->totpAuthenticator->generateSecret();
            $session->set(self::SETUP_SECRET, $secret);
        }
        $pending = new PendingTotpSetup($user->getEmail(), $secret);

        $form = $this->createForm(TwoFactorConfirmationType::class);
        $form->handleRequest($request);

        $limiter = $this->codeCheckLimiter($user);
        if ($form->isSubmitted() && $form->isValid() && $this->isNotThrottled($limiter, $form)) {
            if ($this->totpAuthenticator->checkCode($pending, $this->code($form))) {
                $limiter->reset();
                $backupCodes = $this->backupCodeGenerator->generate();
                $user->enableTwoFactor($secret, $this->encryptor->encrypt($secret), $backupCodes);
                $this->entityManager->flush();

                $session->remove(self::SETUP_SECRET);

                return $this->showNewBackupCodes($request, $backupCodes, 'Two-factor authentication is now enabled.');
            }

            $limiter->consume();
            $form->get('code')->addError(new FormError('The code is not valid. Check that the time on your phone is correct.'));
        }

        return $this->render('profile/two_factor_enable.html.twig', [
            'form' => $form,
            'secret' => $secret,
            'qrCode' => new Builder(writer: new SvgWriter(), data: $this->totpAuthenticator->getQRContent($pending), size: 200, margin: 0)->build()->getDataUri(),
        ]);
    }

    #[Route('/backup-codes', name: 'app_2fa_backup_codes', methods: ['GET'])]
    public function backupCodes(Request $request): Response
    {
        // shown exactly once, right after they were generated
        $backupCodes = $request->getSession()->remove(self::NEW_BACKUP_CODES);
        if (!is_array($backupCodes)) {
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/two_factor_backup_codes.html.twig', ['backupCodes' => $backupCodes]);
    }

    #[Route('/backup-codes/regenerate', name: 'app_2fa_regenerate_backup_codes')]
    public function regenerateBackupCodes(Request $request): Response
    {
        $user = $this->getEnabledUser();
        if (null === $user) {
            return $this->redirectToRoute('app_profile');
        }

        $form = $this->createForm(TwoFactorConfirmationType::class);
        $form->handleRequest($request);

        $limiter = $this->codeCheckLimiter($user);
        if ($form->isSubmitted() && $form->isValid() && $this->isNotThrottled($limiter, $form)) {
            if ($this->totpAuthenticator->checkCode($user, $this->code($form))) {
                $limiter->reset();
                $backupCodes = $this->backupCodeGenerator->generate();
                $user->replaceBackupCodes($backupCodes);
                $this->entityManager->flush();

                return $this->showNewBackupCodes($request, $backupCodes, 'New backup codes generated, the old ones no longer work.');
            }

            $limiter->consume();
            $form->get('code')->addError(new FormError('The code is not valid.'));
        }

        return $this->render('profile/two_factor_confirm.html.twig', [
            'form' => $form,
            'title' => 'New backup codes',
            'intro' => 'All existing backup codes stop working. Confirm with a code from your authenticator app.',
            'submitLabel' => 'Generate new backup codes',
        ]);
    }

    #[Route('/disable', name: 'app_2fa_disable')]
    public function disable(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $this->getEnabledUser();
        if (null === $user) {
            return $this->redirectToRoute('app_profile');
        }

        // a hijacked session alone must not be able to switch 2FA off
        $form = $this->createForm(TwoFactorConfirmationType::class, null, [
            'require_password' => true,
            'code_label' => 'Code from your authenticator app or a backup code',
        ]);
        $form->handleRequest($request);

        $limiter = $this->codeCheckLimiter($user);
        if ($form->isSubmitted() && $form->isValid() && $this->isNotThrottled($limiter, $form)) {
            $code = $this->code($form);
            if (!$passwordHasher->isPasswordValid($user, (string) $form->get('currentPassword')->getData())) {
                $limiter->consume();
                $form->get('currentPassword')->addError(new FormError('Current password is incorrect.'));
            } elseif (!$this->totpAuthenticator->checkCode($user, $code) && !$user->isBackupCode($code)) {
                $limiter->consume();
                $form->get('code')->addError(new FormError('The code is not valid.'));
            } else {
                $limiter->reset();
                $user->disableTwoFactor();
                $this->entityManager->flush();
                $this->addFlash('success', 'Two-factor authentication is now disabled.');

                return $this->redirectToRoute('app_profile');
            }
        }

        return $this->render('profile/two_factor_confirm.html.twig', [
            'form' => $form,
            'title' => 'Disable two-factor authentication',
            'intro' => 'Your account will be protected by your password only.',
            'submitLabel' => 'Disable two-factor authentication',
        ]);
    }

    // a hijacked, logged-in session must not be able to brute-force the 6-digit codes here
    private function codeCheckLimiter(User $user): LimiterInterface
    {
        return $this->codeCheckLimiter->create('user-'.$user->getId());
    }

    /**
     * Only failed checks consume (see the callers), so peek with 0 tokens first.
     *
     * @param FormInterface<array<string, mixed>|null> $form
     */
    private function isNotThrottled(LimiterInterface $limiter, FormInterface $form): bool
    {
        $limit = $limiter->consume(0);
        if ($limit->isAccepted() && $limit->getRemainingTokens() > 0) {
            return true;
        }

        $form->get('code')->addError(new FormError(sprintf(
            'Too many failed attempts, please try again in %d minutes.',
            max(1, (int) ceil(($limit->getRetryAfter()->getTimestamp() - time()) / 60)),
        )));

        return false;
    }

    /**
     * @param list<string> $backupCodes
     */
    private function showNewBackupCodes(Request $request, array $backupCodes, string $message): Response
    {
        $request->getSession()->set(self::NEW_BACKUP_CODES, $backupCodes);
        $this->addFlash('success', $message);

        return $this->redirectToRoute('app_2fa_backup_codes');
    }

    /**
     * @param FormInterface<array<string, mixed>|null> $form
     */
    private function code(FormInterface $form): string
    {
        return trim((string) $form->get('code')->getData());
    }

    private function getEnabledUser(): ?User
    {
        $user = $this->getAppUser();

        return $user->isTotpAuthenticationEnabled() ? $user : null;
    }

    private function getAppUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
