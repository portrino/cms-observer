<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Splits lists people type or systems store, like TYPO3's GeneralUtility::trimExplode():
 * "a,  b , ,c" is ["a", "b", "c"], whatever the spaces around the delimiter.
 */
final class StringList
{
    private function __construct()
    {
    }

    /**
     * @param non-empty-string $delimiter
     *
     * @return list<string> trimmed, without empty parts
     */
    public static function trimExplode(string $delimiter, ?string $list): array
    {
        return array_values(array_filter(
            array_map(trim(...), explode($delimiter, $list ?? '')),
            static fn (string $part): bool => '' !== $part,
        ));
    }
}
