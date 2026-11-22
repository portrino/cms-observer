<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261122232841 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename the timeline notes of calculations to header notes';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE upgrade_calculation_version CHANGE timeline header_notes LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE upgrade_calculation_version CHANGE header_notes timeline LONGTEXT DEFAULT NULL');
    }
}
