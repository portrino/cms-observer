<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126231823 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store remember-me tokens, so logging out deletes the device\'s token';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE rememberme_token (series VARCHAR(88) NOT NULL, value VARCHAR(88) NOT NULL, lastUsed DATETIME NOT NULL, class VARCHAR(100) DEFAULT \'\' NOT NULL, username VARCHAR(200) NOT NULL, PRIMARY KEY (series))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE rememberme_token');
    }
}
