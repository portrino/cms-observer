<?php

declare(strict_types=1);

namespace App\Utility;

/**
 * String helpers, like TYPO3's GeneralUtility::trimExplode().
 */
final class StringUtility
{
    private function __construct()
    {
    }

    /**
     * Splits lists people type or systems store: "a,  b , ,c" is ["a", "b", "c"].
     *
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
