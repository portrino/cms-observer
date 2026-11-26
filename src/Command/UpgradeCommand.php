<?php

declare(strict_types=1);

namespace App\Command;

use App\Setup\ConsoleRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

/**
 * Brings an instance up to the checked-out version: database, assets, cache. Safe to run
 * repeatedly; bin/setup runs it after every update.
 */
#[AsCommand(name: 'app:upgrade', description: 'Creates or migrates the database, builds the assets and clears the cache')]
class UpgradeCommand extends Command
{
    public function __construct(
        private readonly ConsoleRunner $consoleRunner,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
        // Symfony's kernel.secret, which it expects to be set
        #[Autowire(env: 'APP_SECRET')]
        private readonly string $appSecret,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('skip-assets', null, InputOption::VALUE_NONE, 'Don\'t build the assets, e.g. when a build server provides public/assets/');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Upgrading the CMS Observer%s', null !== ($version = $this->checkedOutVersion()) ? ' to '.$version : ''));

        // fail before anything changes, so a deployment stops instead of going live broken
        if ('' === trim($this->appSecret)) {
            $io->error('APP_SECRET is empty. Set it in .env.local, e.g. to the output of "openssl rand -hex 32" (bin/setup generates it on the first run).');

            return Command::FAILURE;
        }

        $steps = [
            'Database' => [['doctrine:database:create', '--if-not-exists'], ['doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration']],
        ];
        if (true !== $input->getOption('skip-assets')) {
            // sass:build downloads the Dart Sass binary on its first run
            $steps['Assets'] = [['importmap:install'], ['sass:build'], ['asset-map:compile']];
        }
        // in a process of its own: a command can't clear the cache of the kernel it runs in
        $steps['Cache'] = [['cache:clear']];

        foreach ($steps as $title => $commands) {
            $io->section($title);
            foreach ($commands as $command) {
                $this->consoleRunner->run($command, $output);
            }
        }

        $io->success('The CMS Observer is up to date.');

        return Command::SUCCESS;
    }

    private function checkedOutVersion(): ?string
    {
        if (!is_dir($this->projectDir.'/.git')) {
            return null;
        }
        $process = new Process(['git', 'describe', '--tags', '--always'], $this->projectDir);
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : null;
    }
}
