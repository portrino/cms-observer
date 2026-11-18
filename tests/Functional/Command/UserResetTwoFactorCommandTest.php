<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\User;
use App\Tests\Functional\CreatesUserTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UserResetTwoFactorCommandTest extends KernelTestCase
{
    use CreatesUserTrait;

    public function testDisablesTwoFactorForTheUser(): void
    {
        $kernel = self::bootKernel();
        // a secret no key can decrypt: the reset is also the way out of a lost TOTP_ENCRYPTION_KEY
        $user = $this->createUser('reset-2fa@example.test', 'Reset', 'password');
        $user->enableTwoFactor('JBSWY3DPEHPK3PXP', 'not-decryptable', ['abcde-fghjk']);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();
        $entityManager->clear(); // the command must load the user from the database

        $tester = new CommandTester(new Application($kernel)->find('app:user:reset-2fa'));

        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'reset-2fa@example.test']));
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'reset-2fa@example.test']);
        self::assertFalse($user?->isTotpAuthenticationEnabled());
        self::assertSame(0, $user->getRemainingBackupCodeCount());
        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'nobody@example.test']));
    }
}
