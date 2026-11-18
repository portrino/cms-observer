<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\BackupCodeGenerator;
use PHPUnit\Framework\TestCase;

class BackupCodesTest extends TestCase
{
    public function testGeneratesTenDistinctReadableCodes(): void
    {
        $codes = new BackupCodeGenerator()->generate();

        self::assertCount(BackupCodeGenerator::COUNT, array_unique($codes));
        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^[a-hjkmnp-z2-9]{5}-[a-hjkmnp-z2-9]{5}$/', $code);
        }
    }

    public function testCodesAreStoredHashedAndUsableOnce(): void
    {
        $user = new User('codes@example.test', 'Codes');
        $user->replaceBackupCodes(['abcde-fghjk', 'mnpqr-stuvw']);

        self::assertTrue($user->isBackupCode('ABCDE FGHJK'), 'case and separators are ignored');
        self::assertFalse($user->isBackupCode('abcde-fghjm'));

        $user->invalidateBackupCode('abcde-fghjk');
        self::assertFalse($user->isBackupCode('abcde-fghjk'));
        self::assertTrue($user->isBackupCode('mnpqr-stuvw'));
        self::assertSame(1, $user->getRemainingBackupCodeCount());
    }

    public function testSessionSerializationLeavesOutTheDecryptedSecret(): void
    {
        $user = new User('serialize@example.test', 'Serialize');
        $user->enableTwoFactor('JBSWY3DPEHPK3PXP', 'encrypted-value', []);
        $user->setTotpSecretDecryptor(static fn (string $encrypted): string => 'JBSWY3DPEHPK3PXP');

        $serialized = serialize($user);

        self::assertStringNotContainsString('JBSWY3DPEHPK3PXP', $serialized);
        $restored = unserialize($serialized);
        self::assertInstanceOf(User::class, $restored);
        self::assertSame('encrypted-value', $restored->getEncryptedTotpSecret());
    }
}
