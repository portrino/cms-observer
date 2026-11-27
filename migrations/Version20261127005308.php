<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127005308 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the Packagist versions of TYPO3 packages with the TYPO3 majors they install on';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE typo3_packagist_version (id INT AUTO_INCREMENT NOT NULL, package_name VARCHAR(255) NOT NULL, version VARCHAR(64) NOT NULL, release_date DATETIME DEFAULT NULL, typo3_majors JSON DEFAULT NULL, UNIQUE INDEX typo3_packagist_version (package_name, version), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE typo3_packagist_version');
    }
}
