<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260908214749 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE typo3_core_release (id INT AUTO_INCREMENT NOT NULL, version VARCHAR(32) NOT NULL, release_date DATETIME NOT NULL, type VARCHAR(255) NOT NULL, is_latest TINYINT NOT NULL, is_active TINYINT NOT NULL, insecure TINYINT NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_67C100A0BF1CD3C3 (version), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE typo3_core_release');
    }
}
