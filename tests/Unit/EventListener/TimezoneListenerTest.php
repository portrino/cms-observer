<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\TimezoneListener;
use PHPUnit\Framework\TestCase;

class TimezoneListenerTest extends TestCase
{
    private string $timezone;

    protected function setUp(): void
    {
        $this->timezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
    }

    public function testSetsTheConfiguredTimezone(): void
    {
        new TimezoneListener('Pacific/Auckland')->apply();

        self::assertSame('Pacific/Auckland', date_default_timezone_get());
    }

    public function testKeepsPhpIniWithoutOne(): void
    {
        new TimezoneListener('')->apply();
        new TimezoneListener(null)->apply();

        self::assertSame($this->timezone, date_default_timezone_get());
    }

    public function testRejectsAnInvalidTimezone(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TimezoneListener('Mars/Olympus')->apply();
    }
}
