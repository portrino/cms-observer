<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sends a test mail with the configured mailer, like "Test Mail Setup" in the TYPO3 Install Tool.
 */
class MailTester
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(default:cms_observer.default_mailer_from_address:MAILER_FROM_ADDRESS)%')]
        private readonly string $fromAddress,
        #[Autowire(env: 'MAILER_DSN')]
        private readonly string $mailerDsn,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return string|null the error, null if the mail server accepted the mail
     */
    public function send(string $to): ?string
    {
        $email = new Email()
            ->from($this->fromAddress)
            ->to($to)
            // in the language of whoever sends it from the status page
            ->subject('[CMS Observer] '.$this->translator->trans('mail.test.subject'))
            ->text($this->translator->trans('mail.test.body', [
                'url' => $this->urlGenerator->generate('dashboard_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]));

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            // sent through Messenger, which wraps the transport's error
            while ($e instanceof HandlerFailedException && null !== $e->getPrevious()) {
                $e = $e->getPrevious();
            }

            return $e->getMessage();
        }

        return null;
    }

    public function getFromAddress(): string
    {
        return $this->fromAddress;
    }

    /**
     * The mailer DSN without user and password, e.g. "smtp://smtp.example.com:587".
     */
    public function getTransport(): string
    {
        try {
            $dsn = Dsn::fromString($this->mailerDsn);
        } catch (\InvalidArgumentException) {
            return 'invalid MAILER_DSN';
        }

        return $dsn->getScheme().'://'.$dsn->getHost().(null !== $dsn->getPort() ? ':'.$dsn->getPort() : '');
    }
}
