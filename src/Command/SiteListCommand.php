<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Site;
use App\Repository\SiteRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:site:list', description: 'List the monitored sites with their IDs')]
class SiteListCommand extends Command
{
    public function __construct(private readonly SiteRepository $siteRepository)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = array_map(static fn (Site $site) => [
            $site->getId(),
            $site->getName(),
            $site->getOrganization()?->getName() ?? '',
            $site->getUrl(),
        ], $this->siteRepository->findBy([], ['id' => 'ASC']));

        new SymfonyStyle($input, $output)->table(['ID', 'Name', 'Organization', 'URL'], $rows);

        return Command::SUCCESS;
    }
}
