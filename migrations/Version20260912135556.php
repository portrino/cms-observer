<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260912135556 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_C1EE637C989D9B62 ON organization');
        $this->addSql('ALTER TABLE organization DROP slug');
        $this->addSql('DROP INDEX UNIQ_694309E4989D9B62 ON site');
        $this->addSql('ALTER TABLE site DROP slug');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE organization ADD slug VARCHAR(39) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C1EE637C989D9B62 ON organization (slug)');
        $this->addSql('ALTER TABLE site ADD slug VARCHAR(100) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_694309E4989D9B62 ON site (slug)');
    }
}
