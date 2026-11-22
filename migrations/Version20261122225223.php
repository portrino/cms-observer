<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261122225223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the ELTS end of TYPO3 majors and timeline notes of calculations';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE typo3_core_release ADD elts_until DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE upgrade_calculation_version ADD timeline LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE typo3_core_release DROP elts_until');
        $this->addSql('ALTER TABLE upgrade_calculation_version DROP timeline');
    }
}
