<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260909042943 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE end_of_life_release (id INT AUTO_INCREMENT NOT NULL, product VARCHAR(32) NOT NULL, cycle VARCHAR(32) NOT NULL, is_eol TINYINT NOT NULL, latest_version VARCHAR(32) NOT NULL, eol_url VARCHAR(255) NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX eol_product_cycle (product, cycle), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE site ADD php_insecure TINYINT DEFAULT NULL, ADD php_outdated TINYINT DEFAULT NULL, ADD php_latest_version VARCHAR(32) DEFAULT NULL, ADD php_eol_url VARCHAR(255) DEFAULT NULL, ADD db_insecure TINYINT DEFAULT NULL, ADD db_outdated TINYINT DEFAULT NULL, ADD db_latest_version VARCHAR(32) DEFAULT NULL, ADD db_eol_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE end_of_life_release');
        $this->addSql('ALTER TABLE site DROP php_insecure, DROP php_outdated, DROP php_latest_version, DROP php_eol_url, DROP db_insecure, DROP db_outdated, DROP db_latest_version, DROP db_eol_url');
    }
}
