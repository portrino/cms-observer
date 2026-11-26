<?php

declare(strict_types=1);

namespace App\Command;

use App\Setup\DatabaseServerProbe;
use App\Setup\EnvFileWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Mailer\Transport\Dsn;

/**
 * First step of an installation (bin/setup runs it): asks for the instance's settings,
 * checks them and writes .env.local. Creating the schema, building the assets and the
 * first login are separate commands, so they run with the new .env.local loaded.
 */
#[AsCommand(name: 'app:setup', description: 'Asks for the settings of this instance, checks them and writes .env.local')]
class SetupCommand extends Command
{
    public function __construct(
        private readonly EnvFileWriter $envFileWriter,
        private readonly DatabaseServerProbe $databaseServerProbe,
        #[Autowire('%kernel.project_dir%/.env.local')]
        private readonly string $envFilePath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('observer-url', null, InputOption::VALUE_REQUIRED, 'Public URL of the Observer, used for links in the mails')
            ->addOption('database-url', null, InputOption::VALUE_REQUIRED, 'Database URL, e.g. mysql://user:pass@127.0.0.1:3306/observer')
            ->addOption('mailer-dsn', null, InputOption::VALUE_REQUIRED, 'Mailer DSN, e.g. smtp://user:pass@smtp.example.com:587')
            ->addOption('mailer-from', null, InputOption::VALUE_REQUIRED, 'Sender address of the security mails')
            ->addOption('wordfence-api-key', null, InputOption::VALUE_REQUIRED, 'Wordfence Intelligence API key (optional)')
            ->addOption('packagist-contact-email', null, InputOption::VALUE_REQUIRED, 'Contact address sent to Packagist with its API requests (optional)')
            ->addOption('timezone', null, InputOption::VALUE_REQUIRED, 'Timezone of the times the Observer records and shows, e.g. Europe/Berlin')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite an existing .env.local (its secrets are kept)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('CMS Observer setup');

        $existing = $this->readExistingEnvFile($input, $io);
        if (null === $existing) {
            return Command::FAILURE;
        }

        $observerUrl = $this->askRequired($io, 'Public URL of the Observer', $this->option($input, 'observer-url') ?? $existing['DEFAULT_URI'] ?? null, static function (string $url): string {
            if (false === filter_var($url, \FILTER_VALIDATE_URL) || !in_array(parse_url($url, \PHP_URL_SCHEME), ['http', 'https'], true)) {
                throw new \RuntimeException('Enter a URL like https://observer.example.com.');
            }

            return rtrim($url, '/');
        });
        $databaseUrl = $this->askRequired($io, 'Database URL (mysql://user:password@host:3306/database)', $this->option($input, 'database-url') ?? $existing['DATABASE_URL'] ?? null, $this->checkDatabaseUrl(...));
        $mailerDsn = $this->askRequired($io, 'Mailer DSN (smtp://user:password@host:587)', $this->option($input, 'mailer-dsn') ?? $existing['MAILER_DSN'] ?? null, static function (string $dsn): string {
            Dsn::fromString($dsn);

            return $dsn;
        });
        $mailerFrom = $this->askRequired($io, 'Sender address of the security mails', $this->option($input, 'mailer-from') ?? $existing['MAILER_FROM_ADDRESS'] ?? 'no-reply@'.parse_url($observerUrl, \PHP_URL_HOST), static function (string $address): string {
            if (false === filter_var($address, \FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Enter an email address.');
            }

            return $address;
        });
        $wordfenceApiKey = (string) $io->ask('Wordfence Intelligence API key (optional, checks WordPress plugins for vulnerabilities)', $this->option($input, 'wordfence-api-key') ?? $existing['WORDFENCE_API_KEY'] ?? '');
        // Packagist asks API users for a contact; the Observer asks it for Craft CMS versions and advisories
        $packagistContactEmail = (string) $io->ask('Contact address for Packagist (optional, sent with the Craft CMS version and advisory requests)', $this->option($input, 'packagist-contact-email') ?? $existing['PACKAGIST_CONTACT_EMAIL'] ?? '', self::checkOptionalEmail(...));
        $timezone = $this->askRequired($io, 'Timezone of the times the Observer records and shows', $this->option($input, 'timezone') ?? $existing['APP_TIMEZONE'] ?? date_default_timezone_get(), static function (string $timezone): string {
            if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
                throw new \RuntimeException('Enter a timezone like Europe/Berlin.');
            }

            return $timezone;
        });

        $this->envFileWriter->write($this->envFilePath, [
            'APP_ENV' => 'prod',
            // kept on a re-run: a new TOTP key would lock out every two-factor user
            'APP_SECRET' => $existing['APP_SECRET'] ?? bin2hex(random_bytes(32)),
            'TOTP_ENCRYPTION_KEY' => $existing['TOTP_ENCRYPTION_KEY'] ?? base64_encode(random_bytes(32)),
            'DEFAULT_URI' => $observerUrl,
            'DATABASE_URL' => $databaseUrl,
            'MAILER_DSN' => $mailerDsn,
            'MAILER_FROM_ADDRESS' => $mailerFrom,
            'WORDFENCE_API_KEY' => trim($wordfenceApiKey),
            'PACKAGIST_CONTACT_EMAIL' => $packagistContactEmail,
            // the same for the web server and the scheduler's worker, whatever their php.ini says
            'APP_TIMEZONE' => $timezone,
        ]);

        $io->success(sprintf('Wrote %s.', $this->envFilePath));
        $io->note([
            'Back up TOTP_ENCRYPTION_KEY from .env.local: without it every two-factor login has to be reset.',
            'The file is readable by its group only; the web server user needs to be in that group.',
        ]);

        return Command::SUCCESS;
    }

    /**
     * @return array<string, string>|null null when an existing file must not be overwritten
     */
    private function readExistingEnvFile(InputInterface $input, SymfonyStyle $io): ?array
    {
        if (!is_file($this->envFilePath)) {
            return [];
        }
        if (true !== $input->getOption('force') && !$io->confirm(sprintf('%s exists already. Overwrite it (its secrets are kept)?', $this->envFilePath), false)) {
            $io->error('Nothing changed. Run "bin/console app:upgrade" to update an existing instance, or pass --force.');

            return null;
        }

        return new Dotenv()->parse((string) file_get_contents($this->envFilePath), $this->envFilePath);
    }

    /**
     * Without a serverVersion matching the real server, Doctrine may send SQL the server
     * doesn't understand, so it's taken from the server itself.
     */
    private function checkDatabaseUrl(string $databaseUrl): string
    {
        try {
            $serverVersion = $this->databaseServerProbe->detectServerVersion($databaseUrl);
        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf('Could not connect: %s', $e->getMessage()), previous: $e);
        }

        return DatabaseServerProbe::withServerVersion($databaseUrl, $serverVersion);
    }

    /**
     * @param callable(string): string $validate
     */
    private function askRequired(SymfonyStyle $io, string $question, ?string $default, callable $validate): string
    {
        $answer = (string) $io->ask($question, $default, static function (?string $answer) use ($validate): string {
            if (null === $answer || '' === trim($answer)) {
                throw new \RuntimeException('This value is required.');
            }

            return $validate(trim($answer));
        });
        // without interaction there's no default to validate for a missing option
        if ('' === $answer) {
            throw new \RuntimeException(sprintf('"%s" is required, pass it as an option.', $question));
        }

        return $answer;
    }

    private function option(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return is_string($value) && '' !== $value ? $value : null;
    }

    private static function checkOptionalEmail(?string $address): string
    {
        $address = trim((string) $address);
        if ('' !== $address && false === filter_var($address, \FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Enter an email address or leave it empty.');
        }

        return $address;
    }
}
