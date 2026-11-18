<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261117182214 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE site_event (id INT AUTO_INCREMENT NOT NULL, occurred_at DATETIME NOT NULL, type VARCHAR(255) NOT NULL, subject VARCHAR(255) DEFAULT NULL, old_value VARCHAR(255) DEFAULT NULL, new_value VARCHAR(255) DEFAULT NULL, detail LONGTEXT DEFAULT NULL, site_id INT NOT NULL, INDEX site_event_site_occurred (site_id, occurred_at), INDEX IDX_FFF4DAA2F6BD1646 (site_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE site_event ADD CONSTRAINT FK_FFF4DAA2F6BD1646 FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE site_event DROP FOREIGN KEY FK_FFF4DAA2F6BD1646');
        $this->addSql('DROP TABLE site_event');
    }
}
