<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\MailTester;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MailTesterTest extends TestCase
{
    public function testReturnsTheTransportErrorUnwrappedFromMessenger(): void
    {
        $mailer = self::createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new HandlerFailedException(
            new Envelope(new \stdClass()),
            [new TransportException('Connection could not be established with host "smtp.example.test:587".')],
        ));

        self::assertSame(
            'Connection could not be established with host "smtp.example.test:587".',
            $this->tester($mailer, 'smtp://user:pass@smtp.example.test:587')->send('someone@example.test'),
        );
    }

    public function testReturnsNullWhenTheMailWasAccepted(): void
    {
        self::assertNull($this->tester(self::createStub(MailerInterface::class), 'null://null')->send('someone@example.test'));
    }

    public function testShowsTheTransportWithoutCredentials(): void
    {
        $tester = $this->tester(self::createStub(MailerInterface::class), 'smtp://user:secret@smtp.example.test:587');

        self::assertSame('smtp://smtp.example.test:587', $tester->getTransport());
    }

    private function tester(MailerInterface $mailer, string $dsn): MailTester
    {
        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://cms-observer.test/');

        return new MailTester($mailer, $urlGenerator, 'no-reply@example.test', $dsn, self::createStub(TranslatorInterface::class));
    }
}
