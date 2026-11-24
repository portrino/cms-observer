<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SecurityNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A sync that re-rates what it touched: the ratings are saved before the
 * security notifier sends its mails, so no sync can forget either step.
 */
abstract class AbstractSyncCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SecurityNotifier $securityNotifier,
    ) {
        parent::__construct();
    }

    /**
     * Syncs and re-rates, without flushing.
     *
     * @return string the success message
     */
    abstract protected function syncAndEvaluate(): string;

    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $message = $this->syncAndEvaluate();
        $this->entityManager->flush();
        $this->securityNotifier->flush();

        new SymfonyStyle($input, $output)->success($message);

        return Command::SUCCESS;
    }
}
