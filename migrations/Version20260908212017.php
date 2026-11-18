<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260908212017 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE site (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, token_hash VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, last_seen_at DATETIME DEFAULT NULL, current_core_version VARCHAR(32) DEFAULT NULL, current_php_version VARCHAR(32) DEFAULT NULL, current_db_version VARCHAR(32) DEFAULT NULL, UNIQUE INDEX UNIQ_694309E4B3BC57DA (token_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE site_extension (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, version VARCHAR(32) NOT NULL, active TINYINT NOT NULL, insecure TINYINT DEFAULT NULL, updated_at DATETIME NOT NULL, site_id INT NOT NULL, UNIQUE INDEX site_extension_name (site_id, name), INDEX IDX_F9BD4B02F6BD1646 (site_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE site_report (id INT AUTO_INCREMENT NOT NULL, received_at DATETIME NOT NULL, core_version VARCHAR(32) NOT NULL, php_version VARCHAR(32) NOT NULL, db_version VARCHAR(32) NOT NULL, payload JSON NOT NULL, site_id INT NOT NULL, INDEX IDX_B481D9DBF6BD1646 (site_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE site_extension ADD CONSTRAINT FK_F9BD4B02F6BD1646 FOREIGN KEY (site_id) REFERENCES site (id)');
        $this->addSql('ALTER TABLE site_report ADD CONSTRAINT FK_B481D9DBF6BD1646 FOREIGN KEY (site_id) REFERENCES site (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE site_extension DROP FOREIGN KEY FK_F9BD4B02F6BD1646');
        $this->addSql('ALTER TABLE site_report DROP FOREIGN KEY FK_B481D9DBF6BD1646');
        $this->addSql('DROP TABLE site');
        $this->addSql('DROP TABLE site_extension');
        $this->addSql('DROP TABLE site_report');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
