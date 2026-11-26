<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126144953 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the domains of the last report, so the project search finds a site by them';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site ADD reported_domains LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site DROP reported_domains');
    }
}
