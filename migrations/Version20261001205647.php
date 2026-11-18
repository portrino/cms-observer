<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261001205647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename site_extension to site_package and add package_type, backfilled from each row\'s site type';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE site_extension TO site_package');
        $this->addSql('ALTER TABLE site_package DROP FOREIGN KEY FK_F9BD4B02A123AB1E');
        $this->addSql('ALTER TABLE site_package DROP FOREIGN KEY FK_F9BD4B02F6BD1646');
        $this->addSql('ALTER TABLE site_package RENAME INDEX site_extension_name TO site_package_name');
        $this->addSql('ALTER TABLE site_package RENAME INDEX idx_f9bd4b02a123ab1e TO IDX_25CC855EA123AB1E');
        $this->addSql('ALTER TABLE site_package RENAME INDEX idx_f9bd4b02f6bd1646 TO IDX_25CC855EF6BD1646');
        $this->addSql('ALTER TABLE site_package ADD CONSTRAINT FK_25CC855EA123AB1E FOREIGN KEY (matched_advisory_id) REFERENCES typo3_extension_advisory (id)');
        $this->addSql('ALTER TABLE site_package ADD CONSTRAINT FK_25CC855EF6BD1646 FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE site_package ADD package_type VARCHAR(255) DEFAULT NULL');
        $this->addSql("UPDATE site_package sp INNER JOIN site s ON s.id = sp.site_id SET sp.package_type = CASE s.type WHEN 'typo3' THEN 'typo3-cms-extension' WHEN 'wordpress' THEN 'wordpress-plugin' END");
        $this->addSql('ALTER TABLE site_package MODIFY package_type VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_package DROP FOREIGN KEY FK_25CC855EA123AB1E');
        $this->addSql('ALTER TABLE site_package DROP FOREIGN KEY FK_25CC855EF6BD1646');
        $this->addSql('ALTER TABLE site_package DROP COLUMN package_type');
        $this->addSql('ALTER TABLE site_package RENAME INDEX site_package_name TO site_extension_name');
        $this->addSql('ALTER TABLE site_package ADD CONSTRAINT FK_F9BD4B02A123AB1E FOREIGN KEY (matched_advisory_id) REFERENCES typo3_extension_advisory (id)');
        $this->addSql('ALTER TABLE site_package ADD CONSTRAINT FK_F9BD4B02F6BD1646 FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE');
        $this->addSql('RENAME TABLE site_package TO site_extension');
    }
}
