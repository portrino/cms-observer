<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Data only: package versions reported as "v7.1.1" are stored without the "v" since
 * App\Service\PackageVersion. Their status was computed from the "v" version (always
 * outdated), so it's reset; the next package sync evaluates them again.
 */
final class Version20261118044348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Strip the "v" prefix from stored package versions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE site_package SET version = SUBSTRING(version, 2), outdated = NULL, latest_version = NULL, newer_core_version = NULL, newer_core_package_version = NULL WHERE version REGEXP '^[vV][0-9]'");
    }

    public function down(Schema $schema): void
    {
        // the original spelling isn't known anymore, and the next report sends it again anyway
    }
}
