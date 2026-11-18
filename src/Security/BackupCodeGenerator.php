<?php

declare(strict_types=1);

namespace App\Security;

/**
 * One-time backup codes like "k7mqp-2xt9r": 10 random characters (~50 bits)
 * from an alphabet without look-alikes (0/o, 1/l/i).
 */
class BackupCodeGenerator
{
    private const string ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';
    public const int COUNT = 10;

    /**
     * @return list<string>
     */
    public function generate(): array
    {
        $codes = [];
        while (count($codes) < self::COUNT) {
            $chars = '';
            for ($i = 0; $i < 10; ++$i) {
                $chars .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $codes[substr($chars, 0, 5).'-'.substr($chars, 5)] = true;
        }

        return array_keys($codes);
    }
}
