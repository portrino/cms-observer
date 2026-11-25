<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ObserverRelease;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:sync-observer-release',
    description: 'Fetch the latest CMS Observer release from GitHub, for the update hint in the brand dropdown',
)]
#[AsCronTask('41 4 * * *')] // once a day - releases come every few weeks
class SyncObserverReleaseCommand extends Command
{
    public function __construct(
        private readonly ObserverRelease $observerRelease,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        new SymfonyStyle($input, $output)->success(sprintf('The latest CMS Observer release is %s.', $this->observerRelease->sync()));

        return Command::SUCCESS;
    }
}
