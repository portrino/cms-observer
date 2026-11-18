<?php

declare(strict_types=1);

namespace App\Security;

use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;

/**
 * A TOTP secret during setup, before it's confirmed and stored on the User:
 * lets the bundle's TotpAuthenticator build the QR code and check the first code.
 */
final readonly class PendingTotpSetup implements TwoFactorInterface
{
    public function __construct(
        private string $username,
        private string $secret,
    ) {
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return true;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->username;
    }

    public function getTotpAuthenticationConfiguration(): TotpConfigurationInterface
    {
        // same parameters as User::getTotpAuthenticationConfiguration()
        return new TotpConfiguration($this->secret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }
}
