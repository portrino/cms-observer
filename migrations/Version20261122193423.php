<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261122193423 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add upgrade calculations with immutable versions';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE upgrade_calculation (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, from_major INT NOT NULL, target_major INT NOT NULL, site_id INT NOT NULL, INDEX IDX_F0322601F6BD1646 (site_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE upgrade_calculation_version (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, items JSON NOT NULL, author VARCHAR(100) NOT NULL, note LONGTEXT DEFAULT NULL, hourly_rate DOUBLE PRECISION NOT NULL, management_percent DOUBLE PRECISION NOT NULL, remarks LONGTEXT DEFAULT NULL, final TINYINT NOT NULL, calculation_id INT NOT NULL, INDEX IDX_AA15C87DCE3D4B33 (calculation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE upgrade_calculation ADD CONSTRAINT FK_F0322601F6BD1646 FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE upgrade_calculation_version ADD CONSTRAINT FK_AA15C87DCE3D4B33 FOREIGN KEY (calculation_id) REFERENCES upgrade_calculation (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE upgrade_calculation DROP FOREIGN KEY FK_F0322601F6BD1646');
        $this->addSql('ALTER TABLE upgrade_calculation_version DROP FOREIGN KEY FK_AA15C87DCE3D4B33');
        $this->addSql('DROP TABLE upgrade_calculation');
        $this->addSql('DROP TABLE upgrade_calculation_version');
    }
}
