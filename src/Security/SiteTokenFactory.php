<?php

declare(strict_types=1);

namespace App\Security;

class SiteTokenFactory
{
    /**
     * @return array{0: string, 1: string} [plaintext token, hash to store]
     */
    public function generate(): array
    {
        $token = bin2hex(random_bytes(32));

        return [$token, $this->hash($token)];
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
