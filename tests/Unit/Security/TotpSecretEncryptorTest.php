<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\TotpSecretEncryptor;
use PHPUnit\Framework\TestCase;

class TotpSecretEncryptorTest extends TestCase
{
    public function testRoundTripWithARandomNonce(): void
    {
        $encryptor = new TotpSecretEncryptor(random_bytes(32));

        $first = $encryptor->encrypt('JBSWY3DPEHPK3PXP');
        $second = $encryptor->encrypt('JBSWY3DPEHPK3PXP');

        self::assertNotSame($first, $second);
        self::assertStringNotContainsString('JBSWY3DPEHPK3PXP', $first);
        self::assertSame('JBSWY3DPEHPK3PXP', $encryptor->decrypt($first));
    }

    public function testAnotherKeyCannotDecrypt(): void
    {
        $encrypted = new TotpSecretEncryptor(random_bytes(32))->encrypt('JBSWY3DPEHPK3PXP');

        $this->expectException(\RuntimeException::class);
        new TotpSecretEncryptor(random_bytes(32))->decrypt($encrypted);
    }

    public function testAMissingKeyFailsOnlyWhenUsed(): void
    {
        $encryptor = new TotpSecretEncryptor('');

        $this->expectException(\LogicException::class);
        $encryptor->encrypt('JBSWY3DPEHPK3PXP');
    }
}
