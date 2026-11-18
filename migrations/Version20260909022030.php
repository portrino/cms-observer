<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260909022030 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE organization (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(39) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_C1EE637C989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE site ADD organization_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE site ADD CONSTRAINT FK_694309E432C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('CREATE INDEX IDX_694309E432C8A3DE ON site (organization_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE organization');
        $this->addSql('ALTER TABLE site DROP FOREIGN KEY FK_694309E432C8A3DE');
        $this->addSql('DROP INDEX IDX_694309E432C8A3DE ON site');
        $this->addSql('ALTER TABLE site DROP organization_id');
    }
}
