<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260909063338 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE site_extension DROP FOREIGN KEY `FK_F9BD4B02F6BD1646`');
        $this->addSql('ALTER TABLE site_extension ADD CONSTRAINT FK_F9BD4B02F6BD1646 FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE site_report DROP FOREIGN KEY `FK_B481D9DBF6BD1646`');
        $this->addSql('ALTER TABLE site_report ADD CONSTRAINT FK_B481D9DBF6BD1646 FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE site_extension DROP FOREIGN KEY FK_F9BD4B02F6BD1646');
        $this->addSql('ALTER TABLE site_extension ADD CONSTRAINT `FK_F9BD4B02F6BD1646` FOREIGN KEY (site_id) REFERENCES site (id)');
        $this->addSql('ALTER TABLE site_report DROP FOREIGN KEY FK_B481D9DBF6BD1646');
        $this->addSql('ALTER TABLE site_report ADD CONSTRAINT `FK_B481D9DBF6BD1646` FOREIGN KEY (site_id) REFERENCES site (id)');
    }
}
