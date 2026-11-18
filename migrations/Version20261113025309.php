<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113025309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the reported database platform to site';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site ADD current_db_platform VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site DROP current_db_platform');
    }
}
