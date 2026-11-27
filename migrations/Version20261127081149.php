<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127081149 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the agency\'s own vendors on Packagist';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agency_settings ADD packagist_vendors VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agency_settings DROP packagist_vendors');
    }
}
