<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260909042327 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // This table is a pure cache of TER data (see Typo3ExtensionVersionSynchronizer) -
        // every row is re-derived from TER on the next sync run, so clearing it here
        // instead of backfilling a placeholder for the new NOT NULL `title` column
        // loses nothing.
        $this->addSql('DELETE FROM typo3_ter_extension_version');
        $this->addSql('ALTER TABLE typo3_ter_extension_version ADD title VARCHAR(255) NOT NULL, ADD description LONGTEXT DEFAULT NULL, ADD upload_comment LONGTEXT DEFAULT NULL, ADD state VARCHAR(32) DEFAULT NULL, ADD typo3_versions VARCHAR(255) DEFAULT NULL, ADD upload_date DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE typo3_ter_extension_version DROP title, DROP description, DROP upload_comment, DROP state, DROP typo3_versions, DROP upload_date');
    }
}
