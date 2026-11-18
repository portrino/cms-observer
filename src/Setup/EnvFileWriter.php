<?php

declare(strict_types=1);

namespace App\Setup;

/**
 * Writes the instance's .env.local. Values are double-quoted with Dotenv's escapes,
 * so passwords with quotes, backslashes or "$" survive unchanged.
 */
final class EnvFileWriter
{
    /**
     * @param array<string, string> $values
     */
    public function write(string $path, array $values): void
    {
        $lines = ['# Written by "bin/console app:setup", holds this instance\'s secrets - never commit it'];
        foreach ($values as $name => $value) {
            $lines[] = sprintf('%s="%s"', $name, str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value));
        }

        if (false === file_put_contents($path, implode("\n", $lines)."\n")) {
            throw new \RuntimeException(sprintf('Could not write "%s".', $path));
        }
        // readable by the group, so a web server running as another user of the same group can read it
        chmod($path, 0640);
    }
}
