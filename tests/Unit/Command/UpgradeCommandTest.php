<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\UpgradeCommand;
use App\Setup\ConsoleRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UpgradeCommandTest extends TestCase
{
    public function testRunsDatabaseAssetAndCacheStepsInOrder(): void
    {
        self::assertSame([
            'doctrine:database:create --if-not-exists',
            'doctrine:migrations:migrate --no-interaction --allow-no-migration',
            'importmap:install',
            'sass:build',
            'asset-map:compile',
            'cache:clear',
        ], $this->runUpgrade([]));
    }

    public function testCanSkipTheAssets(): void
    {
        self::assertSame([
            'doctrine:database:create --if-not-exists',
            'doctrine:migrations:migrate --no-interaction --allow-no-migration',
            'cache:clear',
        ], $this->runUpgrade(['--skip-assets' => true]));
    }

    public function testStopsBeforeAnyStepWithoutAnAppSecret(): void
    {
        $runner = $this->createMock(ConsoleRunner::class);
        $runner->expects(self::never())->method('run');

        $tester = new CommandTester(new UpgradeCommand($runner, sys_get_temp_dir(), ''));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('APP_SECRET is empty', $tester->getDisplay());
    }

    /**
     * @param array<string, bool> $options
     *
     * @return list<string> the commands run
     */
    private function runUpgrade(array $options): array
    {
        $commands = [];
        $runner = self::createStub(ConsoleRunner::class);
        $runner->method('run')->willReturnCallback(static function (array $arguments) use (&$commands): void {
            $commands[] = implode(' ', $arguments);
        });

        $tester = new CommandTester(new UpgradeCommand($runner, sys_get_temp_dir(), 'a-secret'));
        self::assertSame(Command::SUCCESS, $tester->execute($options));

        return $commands;
    }
}
