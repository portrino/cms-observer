<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261121130526 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the latest WordPress.org version of the reported themes';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE wordpress_theme_info (id INT AUTO_INCREMENT NOT NULL, updated_at DATETIME NOT NULL, slug VARCHAR(255) NOT NULL, latest_version VARCHAR(32) NOT NULL, requires_wp_version VARCHAR(32) DEFAULT NULL, UNIQUE INDEX UNIQ_61BB1B0E989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE wordpress_theme_info');
    }
}
