<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260908222222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE typo3_extension_advisory (id INT AUTO_INCREMENT NOT NULL, advisory_id VARCHAR(32) NOT NULL, extension_key VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, published_at DATETIME NOT NULL, severity VARCHAR(32) DEFAULT NULL, cve_ids VARCHAR(255) DEFAULT NULL, affected_versions_raw LONGTEXT NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_F74CF42D46CB6A73 (advisory_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE site_extension ADD matched_advisory_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE site_extension ADD CONSTRAINT FK_F9BD4B02A123AB1E FOREIGN KEY (matched_advisory_id) REFERENCES typo3_extension_advisory (id)');
        $this->addSql('CREATE INDEX IDX_F9BD4B02A123AB1E ON site_extension (matched_advisory_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE typo3_extension_advisory');
        $this->addSql('ALTER TABLE site_extension DROP FOREIGN KEY FK_F9BD4B02A123AB1E');
        $this->addSql('DROP INDEX IDX_F9BD4B02A123AB1E ON site_extension');
        $this->addSql('ALTER TABLE site_extension DROP matched_advisory_id');
    }
}
