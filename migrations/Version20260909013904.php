<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260909013904 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Default false for existing rows - no site had this concept before.
        $this->addSql("ALTER TABLE site ADD sla_active TINYINT NOT NULL DEFAULT 0");
        $this->addSql('ALTER TABLE site ALTER sla_active DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE site DROP sla_active');
    }
}
