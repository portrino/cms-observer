<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Demo\DemoSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Demo projects to try the Observer without any site pushing reports, e.g. right after a local
 * installation. Never in production: the projects and their tokens are made up.
 */
#[AsCommand(name: 'app:demo:seed', description: 'Fill the Observer with demo projects; --sync also fetches the release data to rate them')]
class DemoSeedCommand extends Command
{
    /** what rates the demo projects, in the order they depend on each other */
    private const array SYNCS = [
        'app:sync-endoflife-releases',
        'app:typo3:sync-core-releases',
        'app:typo3:sync-extension-advisories',
        'app:typo3:sync-extension-versions',
        'app:typo3:sync-packagist-versions',
        'app:typo3:sync-breaking-commits',
        'app:wordpress:sync-core-releases',
        'app:wordpress:sync-plugins',
        'app:wordpress:sync-themes',
        'app:wordpress:sync-plugin-vulnerabilities',
        'app:craft:sync-advisories',
        'app:craft:sync-core-releases',
        'app:craft:sync-plugin-versions',
    ];

    public function __construct(
        private readonly DemoSeeder $seeder,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('sync', null, InputOption::VALUE_NONE, 'Fetch the release data from TER, Packagist, wordpress.org & co. afterwards, and draft an upgrade calculation')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Add the demo projects although there are projects already');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ('prod' === $this->environment) {
            $io->error('No demo projects in production (APP_ENV=prod).');

            return Command::FAILURE;
        }
        if ($this->seeder->hasProjects() && true !== $input->getOption('force')) {
            $io->error('The Observer has projects already. Add the demo projects anyway with --force.');

            return Command::FAILURE;
        }

        $io->success(sprintf('Created %d demo projects with their reports.', $this->seeder->seedProjects()));
        if (true !== $input->getOption('sync')) {
            $io->note('Rated once the release data is synced: run this with --sync, or let the scheduled syncs come round.');

            return Command::SUCCESS;
        }

        $this->sync($io, $output);
        $this->seeder->seedCalculation()
            ? $io->success('Drafted an upgrade calculation for "Bergblick Tourismus – Website"; refresh it to check the rows a later report changed.')
            : $io->warning('No newer TYPO3 major known, so no upgrade calculation.');

        return Command::SUCCESS;
    }

    private function sync(SymfonyStyle $io, OutputInterface $output): void
    {
        foreach (self::SYNCS as $name) {
            $io->section($name);
            // a failed sync, e.g. without a Wordfence key, leaves its data out, the demo goes on
            if (Command::SUCCESS !== $this->getApplication()?->find($name)->run(new ArrayInput([]), $output)) {
                $io->warning(sprintf('%s failed, its data is missing from the demo.', $name));
            }
        }
    }
}
