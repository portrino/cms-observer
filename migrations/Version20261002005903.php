<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002005903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sessions table for PdoSessionHandler';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sessions (sess_id VARBINARY(128) NOT NULL, sess_data LONGBLOB NOT NULL, sess_lifetime INT UNSIGNED NOT NULL, sess_time INT UNSIGNED NOT NULL, INDEX sess_lifetime_idx (sess_lifetime), PRIMARY KEY (sess_id)) ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sessions');
    }
}
