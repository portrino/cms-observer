<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Hands a loaded user the means to decrypt its TOTP secret, used lazily by
 * getTotpAuthenticationConfiguration() - so only the 2FA check needs the key.
 */
#[AsEntityListener(event: Events::postLoad, method: 'postLoad', entity: User::class)]
class TotpSecretDecryptor
{
    public function __construct(
        private readonly TotpSecretEncryptor $encryptor,
    ) {
    }

    public function postLoad(User $user): void
    {
        $user->setTotpSecretDecryptor($this->encryptor->decrypt(...));
    }
}
