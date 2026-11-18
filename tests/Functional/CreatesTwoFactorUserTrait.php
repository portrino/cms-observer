<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Security\TotpSecretEncryptor;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

trait CreatesTwoFactorUserTrait
{
    use CreatesUserTrait;

    /**
     * Creates a user with TOTP 2FA enabled (password "password").
     *
     * @param list<string> $backupCodes
     *
     * @return string the plain TOTP secret
     */
    private function createTwoFactorUser(string $email, array $backupCodes = ['abcde-fghjk']): string
    {
        $container = static::getContainer();
        $user = $this->createUser($email, '2FA User', 'password');
        $secret = $container->get(TotpAuthenticatorInterface::class)->generateSecret();

        $user->enableTwoFactor($secret, $container->get(TotpSecretEncryptor::class)->encrypt($secret), $backupCodes);
        $container->get(EntityManagerInterface::class)->flush();

        return $secret;
    }

    private static function currentTotpCode(string $secret): string
    {
        return TOTP::createFromSecret('' !== $secret ? $secret : throw new \LogicException('Empty TOTP secret.'))->now();
    }
}
