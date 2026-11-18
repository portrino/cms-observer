<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260913225815 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE wordpress_plugin_version (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(255) NOT NULL, version VARCHAR(32) NOT NULL, release_date DATETIME DEFAULT NULL, requires_wp_version VARCHAR(32) DEFAULT NULL, fetched_at DATETIME NOT NULL, UNIQUE INDEX wordpress_plugin_version (slug, version), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE wordpress_plugin_info ADD description LONGTEXT DEFAULT NULL, ADD bootstrap_filename VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE wordpress_plugin_version');
        $this->addSql('ALTER TABLE wordpress_plugin_info DROP description, DROP bootstrap_filename');
    }
}
