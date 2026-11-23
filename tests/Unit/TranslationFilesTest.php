<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Every key needs a text in every language, otherwise the key itself shows up.
 */
final class TranslationFilesTest extends TestCase
{
    public function testEnglishAndGermanHaveTheSameKeys(): void
    {
        $dir = dirname(__DIR__, 2).'/translations';
        $files = glob($dir.'/*.en.xlf');
        self::assertNotFalse($files);
        self::assertNotEmpty($files);
        foreach ($files as $english) {
            $german = str_replace('.en.xlf', '.de.xlf', $english);
            self::assertFileExists($german);
            self::assertSame($this->keys($english), $this->keys($german), basename($german));
        }
    }

    /** @return list<string> */
    private function keys(string $file): array
    {
        $xml = simplexml_load_file($file);
        self::assertNotFalse($xml);
        $xml->registerXPathNamespace('x', 'urn:oasis:names:tc:xliff:document:1.2');
        $units = $xml->xpath('//x:trans-unit');
        self::assertNotFalse($units);
        $keys = array_map(static fn (\SimpleXMLElement $unit): string => (string) $unit['id'], $units);
        sort($keys);

        return $keys;
    }
}
