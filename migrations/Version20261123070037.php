<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Stores translation keys of history entries and gives the existing ones keys where their
 * English text is one of the known sentences; any other text stays as it is.
 */
final class Version20261123070037 extends AbstractMigration
{
    private const SUBJECTS = [
        '/^No reports$/' => 'event.subject.no_reports',
        '/^Extension (?<name>\S+) (?<version>\S+)$/' => 'event.subject.extension',
        '/^Plugin (?<name>\S+) (?<version>\S+)$/' => 'event.subject.plugin',
        '/^Theme (?<name>\S+) (?<version>\S+)$/' => 'event.subject.theme',
        '/^PHP (?<version>\S+)$/' => 'event.subject.php',
        '/^Database (?<version>\S+)$/' => 'event.subject.db',
        '/^(?<cms>TYPO3|WordPress) core (?<version>\S+)$/' => 'event.subject.core',
        '/^Application context (?<context>.+)$/' => 'event.subject.context',
    ];

    private const DETAILS = [
        '/^No report since (?<since>\d{4}-\d\d-\d\d \d\d:\d\d), the security status is no longer up to date\.$/' => 'event.detail.stale',
        '/^Matches (?<id>\S+) \((?<severity>[^()]+)\): (?<url>\S+)$/' => 'event.detail.advisory',
        '/^(?<id>.+) \((?<severity>[^()]+)\): (?<url>https?:\/\/\S+)$/' => 'event.detail.vulnerability',
        '/^TER marked this exact version insecure \(review_state\)\.$/' => 'event.detail.ter_insecure',
        '/^PHP version is now insecure \(past end of life\)\.$/' => 'event.detail.php_eol',
        '/^PHP version is missing security fixes \(fixed in (?<fixed>\S+)\)\.$/' => 'event.detail.php_missing_fixes',
        '/^PHP version is secure again\.$/' => 'event.detail.php_secure',
        '/^Database version is now insecure \(past end of life\)\.$/' => 'event.detail.db_eol',
        '/^Database version is secure again\.$/' => 'event.detail.db_secure',
        '/^The production site runs outside the Production context: error details and debug output can be public\.$/' => 'event.detail.context_not_production',
        '/^The production site runs in the Production context again\.$/' => 'event.detail.context_production',
        '/^Core version is now insecure \(unmaintained or superseded by a security release\)\.$/' => 'event.detail.core_insecure',
        '/^Core version is secure again\.$/' => 'event.detail.core_secure',
        '/^(?<count>\d+) packages$/' => 'event.detail.packages',
    ];

    public function getDescription(): string
    {
        return 'Store translation keys of history entries, also for the existing ones';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_event ADD messages JSON DEFAULT NULL');
    }

    public function postUp(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative("SELECT id, type, subject, detail FROM site_event WHERE subject IS NOT NULL OR detail IS NOT NULL");
        foreach ($rows as $row) {
            $messages = [];
            if (null !== $row['subject'] && null !== $message = self::match(self::SUBJECTS, (string) $row['subject'])) {
                $messages['subject'] = $message;
            }
            // a rejected report's detail is the list of domains, data not a sentence
            if (null !== $row['detail'] && 'report-rejected' !== $row['type'] && null !== $message = self::match(self::DETAILS, (string) $row['detail'])) {
                $messages['detail'] = $message;
            }
            if ([] !== $messages) {
                $this->connection->executeStatement('UPDATE site_event SET messages = ? WHERE id = ?', [json_encode($messages, \JSON_THROW_ON_ERROR), $row['id']]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_event DROP messages');
    }

    /**
     * @param array<string, string> $patterns
     *
     * @return array{key: string, params: array<string, scalar>}|null
     */
    public static function match(array $patterns, string $text): ?array
    {
        foreach ($patterns as $pattern => $key) {
            if (1 === preg_match($pattern, $text, $matches)) {
                $params = array_filter($matches, 'is_string', \ARRAY_FILTER_USE_KEY);
                if (isset($params['since'])) {
                    $params['since'] = (new \DateTimeImmutable($params['since']))->getTimestamp();
                }
                if (isset($params['count'])) {
                    $params['count'] = (int) $params['count'];
                }
                if ('unknown severity' === ($params['severity'] ?? null)) {
                    $params['severity'] = 'unknown';
                }

                return ['key' => $key, 'params' => $params];
            }
        }

        return null;
    }
}
