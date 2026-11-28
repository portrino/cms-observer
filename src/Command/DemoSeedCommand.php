<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Demo\DemoReleaseData;
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
 * installation or on a demo instance. Only on an Observer without projects, in production even with
 * --force: the demo's made-up projects must not mix with real ones.
 */
#[AsCommand(name: 'app:demo:seed', description: 'Fill the Observer with demo projects; --sync or --offline also adds the release data to rate them')]
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
        private readonly DemoReleaseData $releaseData,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('sync', null, InputOption::VALUE_NONE, 'Fetch the release data from TER, Packagist, wordpress.org & co. afterwards, and draft an upgrade calculation')
            ->addOption('offline', null, InputOption::VALUE_NONE, 'Write a fixed snapshot of the release data instead, without any request; only on an Observer without release data')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Add the demo projects although there are projects already; not with APP_ENV=prod')
            ->addOption('write-snapshot', null, InputOption::VALUE_NONE, 'Write the release data of this Observer as the --offline snapshot (after --sync on an empty database), instead of seeding');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (true === $input->getOption('write-snapshot')) {
            $io->success(sprintf('Wrote %d rows of release data to the snapshot.', $this->releaseData->export()));

            return Command::SUCCESS;
        }
        $offline = true === $input->getOption('offline');
        $refusal = $this->refusal($offline, true === $input->getOption('sync'), true === $input->getOption('force'));
        if (null !== $refusal) {
            $io->error($refusal);

            return Command::FAILURE;
        }

        // before the reports, which are rated as they come in
        if ($offline) {
            $io->success(sprintf('Wrote %d rows of release data from the snapshot.', $this->releaseData->import()));
        }
        $io->success(sprintf('Created %d demo projects with their reports.', $this->seeder->seedProjects()));
        if (!$offline && true !== $input->getOption('sync')) {
            $io->note('Rated once the release data is synced: run this with --sync or --offline, or let the scheduled syncs come round.');

            return Command::SUCCESS;
        }

        if (!$offline) {
            $this->sync($io, $output);
        }
        $this->seeder->seedCalculation()
            ? $io->success('Drafted an upgrade calculation for "Bergblick Tourismus – Website"; refresh it to check the rows a later report changed.')
            : $io->warning('No newer TYPO3 major known, so no upgrade calculation.');

        return Command::SUCCESS;
    }

    /** why the seed doesn't run, if it doesn't */
    private function refusal(bool $offline, bool $sync, bool $force): ?string
    {
        return match (true) {
            'prod' === $this->environment && $force => 'No --force in production (APP_ENV=prod): demo projects only on an Observer without projects.',
            $offline && $sync => 'Either --sync or --offline.',
            $offline && $this->releaseData->hasReleaseData() => 'The Observer has synced release data, which the fixed snapshot would clash with. Use --sync instead.',
            !$force && $this->seeder->hasProjects() => 'The Observer has projects already. Add the demo projects anyway with --force.',
            default => null,
        };
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
