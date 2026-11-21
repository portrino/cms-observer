<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Site;
use App\Entity\SiteEvent;
use App\Entity\SiteEventType;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Collects security findings (a site's core/extension became insecure) during
 * one ingestion request or sync command run, then emails every dashboard user
 * a single summary - never one email per finding, so a single new core
 * security release affecting many sites doesn't flood inboxes.
 *
 * Callers must call flush() once at the end of the run; the collected
 * findings are cleared either way so a later flush() in the same process
 * doesn't resend them.
 */
class SecurityNotifier
{
    /** @var SecurityFinding[] */
    private array $findings = [];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $userRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $fromAddress,
    ) {
    }

    public function addFinding(Site $site, string $subject, string $detail, SiteEventType $type = SiteEventType::BecameInsecure): void
    {
        $url = $this->urlGenerator->generate('dashboard_site_show', ['id' => $site->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $kind = SiteEventType::StoppedReporting === $type ? FindingKind::StoppedReporting : FindingKind::Insecure;
        $this->findings[] = new SecurityFinding($kind, $site->getName(), $url, $subject, $detail);
        // every caller flushes the entity manager after evaluating, which stores this with the new status
        $this->entityManager->persist(new SiteEvent($site, $type, $subject, null, null, $detail));
    }

    /**
     * Records in the history that a finding is resolved. Not mailed: only new problems need attention.
     */
    public function recordResolved(Site $site, string $subject, string $detail): void
    {
        $this->entityManager->persist(new SiteEvent($site, SiteEventType::BecameSecure, $subject, null, null, $detail));
    }

    /**
     * A finding about the Observer itself, e.g. an overdue sync, linked to the status page.
     */
    public function addSystemFinding(string $subject, string $detail): void
    {
        $url = $this->urlGenerator->generate('app_status', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->findings[] = new SecurityFinding(FindingKind::SyncOverdue, 'CMS Observer', $url, $subject, $detail);
    }

    public function flush(): void
    {
        if ([] === $this->findings) {
            return;
        }

        $findings = $this->findings;
        $this->findings = [];

        $recipients = array_map(
            static fn (User $user): Address => new Address($user->getEmail(), $user->getName()),
            $this->userRepository->findAll(),
        );

        if ([] === $recipients) {
            return;
        }

        $groups = [];
        foreach (FindingKind::cases() as $kind) {
            $group = array_values(array_filter($findings, static fn (SecurityFinding $finding): bool => $kind === $finding->kind));
            if ([] !== $group) {
                $groups[] = ['kind' => $kind, 'findings' => $group];
            }
        }

        $email = new TemplatedEmail()
            ->from($this->fromAddress)
            ->to(...$recipients)
            ->subject('[CMS Observer] '.implode(', ', array_map(static fn (array $group): string => $group['kind']->count(count($group['findings'])), $groups)))
            ->htmlTemplate('emails/security_findings.html.twig')
            ->context([
                'groups' => $groups,
                'dashboardUrl' => $this->urlGenerator->generate('dashboard_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            // A misconfigured/unreachable mailer must never break report ingestion
            // or the sync commands that call this - the findings are still saved
            // and visible on the dashboard regardless of whether the email went out.
            $this->logger->error('Failed to send security finding notification email: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }
}
