<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Like TYPO3's SYS/phpTimeZone: one timezone for the web server and the scheduler's worker,
 * whose PHP configurations often differ (e.g. date.timezone only set for PHP-FPM). Dates are
 * stored without a timezone, so a worker in another one would shift every time it records.
 * Without APP_TIMEZONE, php.ini's date.timezone applies.
 */
#[AsEventListener(event: KernelEvents::REQUEST, method: 'apply', priority: 4096)]
#[AsEventListener(event: ConsoleEvents::COMMAND, method: 'apply', priority: 4096)]
final readonly class TimezoneListener
{
    public function __construct(
        #[Autowire(env: 'default::APP_TIMEZONE')]
        private ?string $timezone,
    ) {
    }

    public function apply(): void
    {
        if (null === $this->timezone || '' === $this->timezone) {
            return;
        }
        if (!in_array($this->timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw new \InvalidArgumentException(sprintf('APP_TIMEZONE "%s" is no timezone, use one like "Europe/Berlin".', $this->timezone));
        }

        date_default_timezone_set($this->timezone);
    }
}
