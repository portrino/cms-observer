<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260912152712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE typo3_ter_extension_meta (id INT AUTO_INCREMENT NOT NULL, extension_key VARCHAR(255) NOT NULL, packagist_url VARCHAR(255) DEFAULT NULL, repository_url VARCHAR(255) DEFAULT NULL, manual_url VARCHAR(255) DEFAULT NULL, fetched_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_9F26C8C2D694EA1F (extension_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE typo3_ter_extension_meta');
    }
}
