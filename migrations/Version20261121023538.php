<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261121023538 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tell WordPress themes from plugins in packages and vulnerabilities';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX site_package_name ON site_package');
        $this->addSql('CREATE UNIQUE INDEX site_package_name ON site_package (site_id, package_type, name)');
        $this->addSql('DROP INDEX wordpress_plugin_vulnerability ON wordpress_plugin_vulnerability');
        $this->addSql('DROP INDEX wordpress_plugin_vulnerability_slug ON wordpress_plugin_vulnerability');
        $this->addSql('ALTER TABLE wordpress_plugin_vulnerability ADD package_type VARCHAR(255) DEFAULT \'wordpress-plugin\' NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX wordpress_plugin_vulnerability ON wordpress_plugin_vulnerability (wordfence_id, package_type, slug)');
        $this->addSql('CREATE INDEX wordpress_plugin_vulnerability_slug ON wordpress_plugin_vulnerability (package_type, slug)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX site_package_name ON site_package');
        $this->addSql('CREATE UNIQUE INDEX site_package_name ON site_package (site_id, name)');
        $this->addSql('DROP INDEX wordpress_plugin_vulnerability_slug ON wordpress_plugin_vulnerability');
        $this->addSql('DROP INDEX wordpress_plugin_vulnerability ON wordpress_plugin_vulnerability');
        $this->addSql('ALTER TABLE wordpress_plugin_vulnerability DROP package_type');
        $this->addSql('CREATE INDEX wordpress_plugin_vulnerability_slug ON wordpress_plugin_vulnerability (slug)');
        $this->addSql('CREATE UNIQUE INDEX wordpress_plugin_vulnerability ON wordpress_plugin_vulnerability (wordfence_id, slug)');
    }
}
