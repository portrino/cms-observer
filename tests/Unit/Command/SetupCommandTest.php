<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\SetupCommand;
use App\Setup\DatabaseServerProbe;
use App\Setup\EnvFileWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Dotenv\Dotenv;

class SetupCommandTest extends TestCase
{
    private string $envFile;

    protected function setUp(): void
    {
        $this->envFile = sys_get_temp_dir().'/cms-observer-setup-'.bin2hex(random_bytes(4)).'.env';
    }

    protected function tearDown(): void
    {
        @unlink($this->envFile);
    }

    public function testWritesTheSettingsWithTheServerVersionAndNewSecrets(): void
    {
        $tester = $this->createTester('10.11.19-MariaDB');

        self::assertSame(Command::SUCCESS, $tester->execute($this->options(), ['interactive' => false]));

        $env = $this->readEnvFile();
        self::assertSame('prod', $env['APP_ENV']);
        self::assertSame('https://observer.example.test', $env['DEFAULT_URI']);
        self::assertSame('mysql://u:p@db:3306/observer?serverVersion=10.11.19-MariaDB&charset=utf8mb4', $env['DATABASE_URL']);
        self::assertSame('no-reply@observer.example.test', $env['MAILER_FROM_ADDRESS']);
        self::assertSame(64, strlen($env['APP_SECRET']));
        self::assertSame(32, strlen((string) base64_decode($env['TOTP_ENCRYPTION_KEY'], true)));
        self::assertSame(date_default_timezone_get(), $env['APP_TIMEZONE']);
    }

    public function testAsksInteractively(): void
    {
        $tester = $this->createTester('8.0.46');
        $tester->setInputs(['https://observer.example.test/', 'mysql://u:p@db:3306/observer', 'smtp://localhost:25', 'alerts@example.test', 'wf-key', 'Not/AZone', 'Europe/Berlin']);

        self::assertSame(Command::SUCCESS, $tester->execute([]));

        $env = $this->readEnvFile();
        self::assertSame('https://observer.example.test', $env['DEFAULT_URI']);
        self::assertStringContainsString('serverVersion=8.0.46', $env['DATABASE_URL']);
        self::assertSame('alerts@example.test', $env['MAILER_FROM_ADDRESS']);
        self::assertSame('wf-key', $env['WORDFENCE_API_KEY']);
        self::assertSame('Europe/Berlin', $env['APP_TIMEZONE']);
    }

    public function testFailsWhenTheDatabaseCantBeReached(): void
    {
        $probe = self::createStub(DatabaseServerProbe::class);
        $probe->method('detectServerVersion')->willThrowException(new \RuntimeException('Access denied for user "u"'));
        $tester = new CommandTester(new SetupCommand(new EnvFileWriter(), $probe, $this->envFile));

        try {
            $tester->execute($this->options(), ['interactive' => false]);
            self::fail('Expected the database check to fail');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Access denied', $e->getMessage());
        }
        self::assertFileDoesNotExist($this->envFile);
    }

    public function testKeepsAnExistingFileWithoutForce(): void
    {
        file_put_contents($this->envFile, "APP_SECRET=old\n");

        self::assertSame(Command::FAILURE, $this->createTester('8.0.46')->execute($this->options(), ['interactive' => false]));
        self::assertSame("APP_SECRET=old\n", file_get_contents($this->envFile));
    }

    /**
     * A new TOTP key would lock out every two-factor user, a new app secret every session.
     */
    public function testKeepsTheSecretsWhenOverwriting(): void
    {
        file_put_contents($this->envFile, "APP_SECRET=old-secret\nTOTP_ENCRYPTION_KEY=old-key\nMAILER_DSN=smtp://old:25\n");

        self::assertSame(Command::SUCCESS, $this->createTester('8.0.46')->execute(['--force' => true] + $this->options(), ['interactive' => false]));

        $env = $this->readEnvFile();
        self::assertSame('old-secret', $env['APP_SECRET']);
        self::assertSame('old-key', $env['TOTP_ENCRYPTION_KEY']);
        self::assertSame('smtp://localhost:25', $env['MAILER_DSN']);
    }

    public function testNeedsTheRequiredOptionsWithoutInteraction(): void
    {
        $this->expectExceptionMessageMatches('/Database URL.*is required/');

        $this->createTester('8.0.46')->execute(['--observer-url' => 'https://observer.example.test'], ['interactive' => false]);
    }

    /**
     * @return array<string, string>
     */
    private function options(): array
    {
        return [
            '--observer-url' => 'https://observer.example.test',
            '--database-url' => 'mysql://u:p@db:3306/observer',
            '--mailer-dsn' => 'smtp://localhost:25',
        ];
    }

    private function createTester(string $serverVersion): CommandTester
    {
        $probe = self::createStub(DatabaseServerProbe::class);
        $probe->method('detectServerVersion')->willReturn($serverVersion);

        return new CommandTester(new SetupCommand(new EnvFileWriter(), $probe, $this->envFile));
    }

    /**
     * @return array<string, string>
     */
    private function readEnvFile(): array
    {
        return new Dotenv()->parse((string) file_get_contents($this->envFile));
    }
}
