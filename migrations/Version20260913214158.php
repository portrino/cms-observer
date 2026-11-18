<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260913214158 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE wordpress_core_release (id INT AUTO_INCREMENT NOT NULL, version VARCHAR(32) NOT NULL, latest_version VARCHAR(32) NOT NULL, insecure TINYINT NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_ECAA07ABBF1CD3C3 (version), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE wordpress_plugin_info (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(255) NOT NULL, latest_version VARCHAR(32) NOT NULL, requires_wp_version VARCHAR(32) DEFAULT NULL, tested_up_to_wp_version VARCHAR(32) DEFAULT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_805A90B989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE wordpress_core_release');
        $this->addSql('DROP TABLE wordpress_plugin_info');
    }
}
