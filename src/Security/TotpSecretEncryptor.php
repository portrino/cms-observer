<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Encrypts TOTP secrets at rest (libsodium secretbox), so a database dump
 * alone doesn't hand out working second factors. The key is only checked
 * when used: without it, logins of users without 2FA keep working, while
 * 2FA users fail closed instead of bypassing the second factor.
 */
class TotpSecretEncryptor
{
    public function __construct(
        #[Autowire(env: 'base64:TOTP_ENCRYPTION_KEY')]
        #[\SensitiveParameter]
        private readonly string $key,
    ) {
    }

    public function encrypt(string $secret): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($secret, $nonce, $this->key()));
    }

    public function decrypt(string $encrypted): string
    {
        $decoded = base64_decode($encrypted, true);
        if (false === $decoded || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Malformed encrypted TOTP secret.');
        }

        $secret = sodium_crypto_secretbox_open(
            substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key(),
        );
        if (false === $secret) {
            throw new \RuntimeException('Cannot decrypt the TOTP secret - wrong or changed TOTP_ENCRYPTION_KEY?');
        }

        return $secret;
    }

    public function isConfigured(): bool
    {
        return SODIUM_CRYPTO_SECRETBOX_KEYBYTES === strlen($this->key);
    }

    private function key(): string
    {
        if (!$this->isConfigured()) {
            throw new \LogicException('TOTP_ENCRYPTION_KEY must be 32 random bytes, base64-encoded: php -r "echo base64_encode(random_bytes(32));"');
        }

        return $this->key;
    }
}
