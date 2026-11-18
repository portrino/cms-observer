<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261110031515 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional TOTP two-factor authentication to user (encrypted secret, hashed backup codes)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD encrypted_totp_secret LONGTEXT DEFAULT NULL, ADD backup_code_hashes JSON NOT NULL, ADD two_factor_enabled_at DATETIME DEFAULT NULL');
        // existing rows get an empty string, which Doctrine can't hydrate into the array property
        $this->addSql("UPDATE user SET backup_code_hashes = '[]'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP encrypted_totp_secret, DROP backup_code_hashes, DROP two_factor_enabled_at');
    }
}
