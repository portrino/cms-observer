<?php

declare(strict_types=1);

namespace App\Entity;

enum SiteEventType: string
{
    case FirstReport = 'first-report';
    case CoreChanged = 'core-changed';
    case PhpChanged = 'php-changed';
    case DbChanged = 'db-changed';
    case ClientChanged = 'client-changed';
    case PackageInstalled = 'package-installed';
    case PackageUpdated = 'package-updated';
    case PackageRemoved = 'package-removed';
    case PackageActivated = 'package-activated';
    case PackageDeactivated = 'package-deactivated';
    case ReportRejected = 'report-rejected';
    case BecameInsecure = 'became-insecure';
    case StoppedReporting = 'stopped-reporting';

    public function label(): string
    {
        return match ($this) {
            self::FirstReport => 'First report',
            self::CoreChanged => 'Core version changed',
            self::PhpChanged => 'PHP version changed',
            self::DbChanged => 'Database version changed',
            self::ClientChanged => 'Client version changed',
            self::PackageInstalled => 'Package installed',
            self::PackageUpdated => 'Package version changed',
            self::PackageRemoved => 'Package removed',
            self::PackageActivated => 'Package activated',
            self::PackageDeactivated => 'Package deactivated',
            self::ReportRejected => 'Report rejected',
            self::BecameInsecure => 'Became insecure',
            self::StoppedReporting => 'Stopped reporting',
        };
    }
}
