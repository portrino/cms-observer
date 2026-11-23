<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SecurityNotifier;
use App\Service\SyncMonitor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;
use Symfony\Component\Translation\TranslatableMessage;

#[AsCommand(
    name: 'app:check-syncs',
    description: 'Mail about scheduled syncs that missed their last three runs, once per outage',
)]
#[AsCronTask('9 * * * *')] // hourly - each outage is notified once, so this only shortens the delay
class CheckSyncsCommand extends Command
{
    public function __construct(
        private readonly SyncMonitor $syncMonitor,
        private readonly EntityManagerInterface $entityManager,
        private readonly SecurityNotifier $securityNotifier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $overdue = $this->syncMonitor->takeNewlyOverdue();

        foreach ($overdue as $status) {
            // server times, like the status page
            $detail = new TranslatableMessage(null !== $status->getLastSucceededAt() ? 'mail.sync.since' : 'mail.sync.never', [
                'since' => $status->getLastSucceededAt()?->format('Y-m-d H:i') ?? '',
                'failed' => $status->getLastFailedAt()?->format('Y-m-d H:i') ?? 'none',
                'error' => (string) $status->getLastError(),
            ]);
            $this->securityNotifier->addSystemFinding(new TranslatableMessage('mail.sync.subject', ['command' => $status->getCommand()]), $detail);
        }

        $this->entityManager->flush();
        $this->securityNotifier->flush();

        new SymfonyStyle($input, $output)->success(sprintf('%d sync(s) newly overdue.', count($overdue)));

        return Command::SUCCESS;
    }
}
