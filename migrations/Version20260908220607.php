<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260908220607 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE site ADD core_insecure TINYINT DEFAULT NULL, ADD core_outdated TINYINT DEFAULT NULL, ADD core_latest_version VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE typo3_core_release ADD latest_version VARCHAR(32) NOT NULL, DROP is_latest');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE site DROP core_insecure, DROP core_outdated, DROP core_latest_version');
        $this->addSql('ALTER TABLE typo3_core_release ADD is_latest TINYINT NOT NULL, DROP latest_version');
    }
}
