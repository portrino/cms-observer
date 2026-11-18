<?php

declare(strict_types=1);

namespace App\Setup;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

/**
 * Runs a bin/console command in a process of its own, e.g. cache:clear, which can't
 * clear the cache of the kernel it runs in.
 */
class ConsoleRunner
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @param list<string> $arguments
     */
    public function run(array $arguments, OutputInterface $output): void
    {
        $process = new Process([\PHP_BINARY, 'bin/console', ...$arguments], $this->projectDir, timeout: null);
        $process->run(static function (string $type, string $buffer) use ($output): void {
            $output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf('"bin/console %s" failed.', implode(' ', $arguments)));
        }
    }
}
