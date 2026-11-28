<?php

declare(strict_types=1);

namespace App\Service\Demo;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A fixed snapshot of the synced release data (cores, TER, Packagist, wordpress.org, advisories,
 * breaking commits) for the demo projects, so `app:demo:seed --offline` rates them the same way
 * every time and without any request, e.g. for the E2E tests.
 * The rows are copied table by table as the database holds them; written after a
 * `app:demo:seed --sync` on an empty database, which then holds only the demo's data.
 */
class DemoReleaseData
{
    /**
     * table => condition of the rows to keep; in the order of their foreign keys
     * (site_package references the advisories, but they are written before any site).
     */
    private const array TABLES = [
        'end_of_life_release' => null,
        'typo3_core_release' => null,
        'typo3_extension_advisory' => null,
        'typo3_ter_extension_meta' => null,
        'typo3_ter_extension_version' => null,
        'typo3_packagist_version' => null,
        'wordpress_core_release' => null,
        'wordpress_plugin_info' => null,
        'wordpress_plugin_version' => null,
        'wordpress_theme_info' => null,
        // Wordfence sends the vulnerabilities of every plugin, ~50,000
        'wordpress_plugin_vulnerability' => 'slug IN (SELECT name FROM site_package)',
        'craft_core_release' => null,
        'craft_advisory' => null,
        'craft_plugin_version' => null,
    ];

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.project_dir%/resources/demo/release-data.json')]
        private readonly string $file,
    ) {
    }

    /** Whether any release data was synced already: the snapshot would clash with it */
    public function hasReleaseData(): bool
    {
        return array_any(array_keys(self::TABLES), fn (string $table): bool => false !== $this->connection->fetchOne(sprintf('SELECT 1 FROM %s LIMIT 1', $table)));
    }

    /**
     * @return int rows written
     */
    public function import(): int
    {
        /** @var array<string, list<array<string, mixed>>> $tables */
        $tables = json_decode((string) file_get_contents($this->file), true, flags: JSON_THROW_ON_ERROR);
        $count = 0;
        $this->connection->transactional(function () use ($tables, &$count): void {
            foreach (array_keys(self::TABLES) as $table) {
                foreach ($tables[$table] ?? [] as $row) {
                    $this->connection->insert($table, $row);
                    ++$count;
                }
            }
        });

        return $count;
    }

    /**
     * @return int rows written to the snapshot
     */
    public function export(): int
    {
        $lines = [];
        $count = 0;
        foreach (self::TABLES as $table => $condition) {
            $rows = $this->connection->fetchAllAssociative(sprintf('SELECT * FROM %s%s ORDER BY id', $table, null === $condition ? '' : ' WHERE '.$condition));
            $count += count($rows);
            // a row per line, so a new snapshot shows as a readable diff
            $lines[] = json_encode($table).': ['.implode(',', array_map(static fn (array $row): string => "\n".json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $rows))."\n]";
        }
        file_put_contents($this->file, "{\n".implode(",\n", $lines)."\n}\n");

        return $count;
    }
}
