<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCreateCommandTest extends KernelTestCase
{
    public function testAsksForMissingValuesWithAHiddenPassword(): void
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('app:user:create'));
        $tester->setInputs(['setup-admin@example.test', 'Setup Admin', 'secret-password']);

        self::assertSame(Command::SUCCESS, $tester->execute([]));

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'setup-admin@example.test']);
        self::assertSame('Setup Admin', $user?->getName());
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'secret-password'));
    }

    public function testStillTakesAllValuesAsArguments(): void
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('app:user:create'));

        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'args@example.test', 'name' => 'Args', 'password' => 'secret'], ['interactive' => false]));
        self::assertNotNull(static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'args@example.test']));
    }
}
