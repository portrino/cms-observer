<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127161523 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the source of TYPO3 Packagist versions and their breaking commits from GitHub';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE typo3_packagist_version ADD breaking_commits JSON DEFAULT NULL, ADD source_url VARCHAR(255) DEFAULT NULL, ADD source_reference VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE typo3_packagist_version DROP breaking_commits, DROP source_url, DROP source_reference');
    }
}
