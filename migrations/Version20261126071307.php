<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261126071307 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the Packagist versions of Craft plugins and the advisory affecting a reported plugin';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE craft_plugin_version (id INT AUTO_INCREMENT NOT NULL, package_name VARCHAR(255) NOT NULL, version VARCHAR(64) NOT NULL, release_date DATETIME DEFAULT NULL, craft_constraint VARCHAR(255) DEFAULT NULL, UNIQUE INDEX craft_plugin_version (package_name, version), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE site_package ADD matched_craft_advisory_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE site_package ADD CONSTRAINT FK_25CC855EF81B4B34 FOREIGN KEY (matched_craft_advisory_id) REFERENCES craft_advisory (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_25CC855EF81B4B34 ON site_package (matched_craft_advisory_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE craft_plugin_version');
        $this->addSql('ALTER TABLE site_package DROP FOREIGN KEY FK_25CC855EF81B4B34');
        $this->addSql('DROP INDEX IDX_25CC855EF81B4B34 ON site_package');
        $this->addSql('ALTER TABLE site_package DROP matched_craft_advisory_id');
    }
}
