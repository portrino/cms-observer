<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup;

use App\Setup\EnvFileWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

class EnvFileWriterTest extends TestCase
{
    public function testValuesSurviveDotenvParsingUnchanged(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'env');
        $values = [
            'DATABASE_URL' => 'mysql://user:p"a$s\word\'@127.0.0.1:3306/observer?serverVersion=8.0.46',
            'WORDFENCE_API_KEY' => '',
            'APP_SECRET' => 'abc#not-a-comment',
        ];

        new EnvFileWriter()->write($path, $values);

        self::assertSame($values, new Dotenv()->parse((string) file_get_contents($path)));
        self::assertSame('0640', substr(sprintf('%o', fileperms($path)), -4));
        unlink($path);
    }
}
