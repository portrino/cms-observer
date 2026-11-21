<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261121003653 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track when each scheduled sync last succeeded';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sync_status (last_succeeded_at DATETIME DEFAULT NULL, overdue_notified_at DATETIME DEFAULT NULL, command VARCHAR(128) NOT NULL, tracked_since DATETIME NOT NULL, PRIMARY KEY (command)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE sync_status');
    }
}
