<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260909002016 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add with a temporary default so existing rows don't violate NOT NULL,
        // then backfill from the email's local part and drop the default.
        $this->addSql("ALTER TABLE user ADD name VARCHAR(255) NOT NULL DEFAULT ''");
        $this->addSql("UPDATE user SET name = SUBSTRING_INDEX(email, '@', 1) WHERE name = ''");
        $this->addSql('ALTER TABLE user ALTER name DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP name');
    }
}
