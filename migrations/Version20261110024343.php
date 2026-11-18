<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261110024343 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add wordpress_plugin_vulnerability (Wordfence feed) and the matched vulnerability on site_package';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE wordpress_plugin_vulnerability (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(512) NOT NULL, affected_versions JSON NOT NULL, patched_versions JSON NOT NULL, informational TINYINT NOT NULL, cvss_score DOUBLE PRECISION DEFAULT NULL, cvss_rating VARCHAR(16) DEFAULT NULL, cve VARCHAR(32) DEFAULT NULL, url VARCHAR(512) NOT NULL, copyright_notices JSON NOT NULL, published_at DATETIME DEFAULT NULL, source_updated_at DATETIME DEFAULT NULL, wordfence_id VARCHAR(36) NOT NULL, slug VARCHAR(255) NOT NULL, INDEX wordpress_plugin_vulnerability_slug (slug), UNIQUE INDEX wordpress_plugin_vulnerability (wordfence_id, slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE site_package ADD matched_wordpress_vulnerability_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE site_package ADD CONSTRAINT FK_25CC855EE611CF03 FOREIGN KEY (matched_wordpress_vulnerability_id) REFERENCES wordpress_plugin_vulnerability (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_25CC855EE611CF03 ON site_package (matched_wordpress_vulnerability_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE site_package DROP FOREIGN KEY FK_25CC855EE611CF03');
        $this->addSql('DROP INDEX IDX_25CC855EE611CF03 ON site_package');
        $this->addSql('ALTER TABLE site_package DROP matched_wordpress_vulnerability_id');
        $this->addSql('DROP TABLE wordpress_plugin_vulnerability');
    }
}
