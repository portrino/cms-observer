<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261110020255 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add extended_support_ended to end_of_life_release for the TYPO3 major support status';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE end_of_life_release ADD extended_support_ended TINYINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE end_of_life_release DROP extended_support_ended');
    }
}
