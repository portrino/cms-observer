<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260909024917 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add with a temporary default so existing rows don't violate NOT NULL,
        // backfill a domain-derived slug from each site's URL (matching
        // SiteSlugSuggester: strip scheme/path, dots -> dashes), drop the
        // default, then add the unique index once values are distinct.
        $this->addSql("ALTER TABLE site ADD slug VARCHAR(100) NOT NULL DEFAULT ''");
        $this->addSql("UPDATE site SET slug = LOWER(REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(url, '://', -1), '/', 1), '.', '-')) WHERE slug = ''");
        $this->addSql('ALTER TABLE site ALTER slug DROP DEFAULT');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_694309E4989D9B62 ON site (slug)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_694309E4989D9B62 ON site');
        $this->addSql('ALTER TABLE site DROP slug');
    }
}
