<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * No #[UniqueEntity] here: ProfileType is a plain unmapped form
 * (data_class: null), so the validator never sees an actual User instance
 * to check uniqueness against. ProfileController checks findOneByEmail()
 * explicitly instead.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface, BackupCodeInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var string[] */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    // Optional TOTP 2FA: stored encrypted (TotpSecretEncryptor), decrypted only when the 2FA check needs it
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $encryptedTotpSecret = null;

    private ?string $totpSecret = null;

    /** @var (\Closure(string): string)|null set on load by TotpSecretDecryptor */
    private ?\Closure $totpSecretDecryptor = null;

    /** @var list<string> SHA-256 hashes of the unused backup codes, never the codes themselves */
    #[ORM\Column(type: Types::JSON)]
    private array $backupCodeHashes = [];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $twoFactorEnabledAt = null;

    // language of the interface, e.g. "de"; null: the browser's language
    #[ORM\Column(length: 5, nullable: true)]
    private ?string $locale = null;

    public function __construct(
        #[ORM\Column(length: 180, unique: true)]
        private string $email,
        #[ORM\Column(length: 255)]
        private string $name,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getInitials(): string
    {
        $words = preg_split('/\s+/', trim($this->name), -1, PREG_SPLIT_NO_EMPTY);

        if ([] === $words || false === $words) {
            return '?';
        }

        $initials = array_map(
            static fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)),
            array_slice($words, 0, 2),
        );

        return implode('', $initials);
    }

    public function getUserIdentifier(): string
    {
        if ('' === $this->email) {
            throw new \LogicException('User has no email.');
        }

        return $this->email;
    }

    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param string[] $roles
     */
    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    /**
     * @param list<string> $backupCodes plain codes, only kept as hashes
     */
    public function enableTwoFactor(string $totpSecret, string $encryptedTotpSecret, array $backupCodes): void
    {
        $this->totpSecret = $totpSecret;
        $this->encryptedTotpSecret = $encryptedTotpSecret;
        $this->twoFactorEnabledAt = new \DateTimeImmutable();
        $this->replaceBackupCodes($backupCodes);
    }

    public function disableTwoFactor(): void
    {
        $this->totpSecret = null;
        $this->encryptedTotpSecret = null;
        $this->twoFactorEnabledAt = null;
        $this->backupCodeHashes = [];
    }

    /**
     * @param list<string> $backupCodes
     */
    public function replaceBackupCodes(array $backupCodes): void
    {
        $this->backupCodeHashes = array_map(self::hashBackupCode(...), $backupCodes);
    }

    /**
     * Symfony keeps the user in the session token: neither the decrypted TOTP secret (the DB-stored
     * session would defeat the encryption) nor the decryptor closure (not serializable) go there.
     * The user is reloaded from the database on every request anyway. Without __unserialize(),
     * PHP restores the remaining properties as they are.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $data = get_object_vars($this);
        unset($data['totpSecret'], $data['totpSecretDecryptor']);

        return $data;
    }

    public function getEncryptedTotpSecret(): ?string
    {
        return $this->encryptedTotpSecret;
    }

    /**
     * Lazy on purpose: loading a user (e.g. for the notification mail or app:user:reset-2fa)
     * must keep working without TOTP_ENCRYPTION_KEY, only the 2FA check itself needs it.
     *
     * @param \Closure(string): string $decryptor
     */
    public function setTotpSecretDecryptor(\Closure $decryptor): void
    {
        $this->totpSecretDecryptor = $decryptor;
    }

    public function getTwoFactorEnabledAt(): ?\DateTimeImmutable
    {
        return $this->twoFactorEnabledAt;
    }

    public function getRemainingBackupCodeCount(): int
    {
        return count($this->backupCodeHashes);
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return null !== $this->encryptedTotpSecret;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->email;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if (null === $this->totpSecret && null !== $this->encryptedTotpSecret && null !== $this->totpSecretDecryptor) {
            // throws without the right key: the 2FA check fails closed instead of being skipped
            $this->totpSecret = ($this->totpSecretDecryptor)($this->encryptedTotpSecret);
        }

        // the defaults every authenticator app supports (RFC 6238)
        return null === $this->totpSecret ? null : new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    public function isBackupCode(string $code): bool
    {
        $hash = self::hashBackupCode($code);

        return [] !== array_filter($this->backupCodeHashes, static fn (string $stored): bool => hash_equals($stored, $hash));
    }

    public function invalidateBackupCode(string $code): void
    {
        $hash = self::hashBackupCode($code);
        $this->backupCodeHashes = array_values(array_filter($this->backupCodeHashes, static fn (string $stored): bool => !hash_equals($stored, $hash)));
    }

    // random ~50-bit codes behind login throttling: a fast hash is enough, no password hashing needed
    private static function hashBackupCode(string $code): string
    {
        return hash('sha256', strtolower((string) preg_replace('/[\s-]+/', '', $code)));
    }
}
