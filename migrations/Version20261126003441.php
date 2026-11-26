<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261126003441 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the Craft CMS core releases';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE craft_core_release (id INT AUTO_INCREMENT NOT NULL, updated_at DATETIME NOT NULL, version VARCHAR(32) NOT NULL, latest_version VARCHAR(32) NOT NULL, insecure TINYINT NOT NULL, release_date DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_78FA797BF1CD3C3 (version), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE craft_core_release');
    }
}
