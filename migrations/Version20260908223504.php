<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260908223504 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE typo3_ter_extension_version (id INT AUTO_INCREMENT NOT NULL, extension_key VARCHAR(255) NOT NULL, version VARCHAR(32) NOT NULL, insecure TINYINT NOT NULL, fetched_at DATETIME NOT NULL, UNIQUE INDEX ter_extension_version (extension_key, version), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE site_extension ADD outdated TINYINT DEFAULT NULL, ADD latest_version VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE typo3_ter_extension_version');
        $this->addSql('ALTER TABLE site_extension DROP outdated, DROP latest_version');
    }
}
