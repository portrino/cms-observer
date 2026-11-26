<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261126070536 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the Packagist advisories of Craft CMS and its plugins';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE craft_advisory (id INT AUTO_INCREMENT NOT NULL, updated_at DATETIME NOT NULL, advisory_id VARCHAR(32) NOT NULL, package_name VARCHAR(255) NOT NULL, title LONGTEXT NOT NULL, link VARCHAR(255) NOT NULL, affected_versions LONGTEXT NOT NULL, cve VARCHAR(32) DEFAULT NULL, severity VARCHAR(16) DEFAULT NULL, UNIQUE INDEX UNIQ_8640AF1346CB6A73 (advisory_id), INDEX craft_advisory_package (package_name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE craft_advisory');
    }
}
