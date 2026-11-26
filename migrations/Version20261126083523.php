<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261126083523 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make room for longer package versions, e.g. branch names';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_package CHANGE version version VARCHAR(64) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_package CHANGE version version VARCHAR(32) NOT NULL');
    }
}
