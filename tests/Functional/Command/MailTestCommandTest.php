<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class MailTestCommandTest extends KernelTestCase
{
    public function testSendsATestMail(): void
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('app:mail:test'));

        self::assertSame(Command::SUCCESS, $tester->execute(['recipient' => 'someone@example.test']));
        self::assertEmailCount(1);
        self::assertStringContainsString('accepted the test mail', $tester->getDisplay());
    }

    public function testRejectsAnInvalidAddress(): void
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('app:mail:test'));

        self::assertSame(Command::FAILURE, $tester->execute(['recipient' => 'not-an-address']));
        self::assertEmailCount(0);
    }
}
