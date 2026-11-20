<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Tests\Functional\CreatesTwoFactorUserTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TwoFactorLoginTest extends WebTestCase
{
    use CreatesTwoFactorUserTrait;

    public function testUserWithoutTwoFactorLogsInWithThePasswordOnly(): void
    {
        $client = $this->createLoginClient();
        $this->createUser('no-2fa@example.test', 'No 2FA', 'password');

        $this->submitLogin($client, 'no-2fa@example.test');

        self::assertResponseRedirects('/');
    }

    public function testTwoFactorUserNeedsAValidCode(): void
    {
        $client = $this->createLoginClient();
        $secret = $this->createTwoFactorUser('with-2fa@example.test', ['abcde-fghjk']);

        $this->submitLogin($client, 'with-2fa@example.test');

        // password accepted, but the dashboard stays locked behind the code form
        $client->request('GET', '/');
        self::assertResponseRedirects('/2fa');
        $client->request('GET', '/projects/new');
        self::assertResponseRedirects('/2fa');

        $this->submitCode($client, '000000');
        self::assertResponseRedirects('/2fa');
        $client->followRedirect();
        self::assertSelectorExists('.alert-danger');

        // back to the page requested before the code form
        $this->submitCode($client, self::currentTotpCode($secret));
        self::assertResponseRedirects('/projects/new');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testCodeFormIsThrottled(): void
    {
        $client = $this->createLoginClient();
        $secret = $this->createTwoFactorUser('throttled-2fa@example.test');

        $this->submitLogin($client, 'throttled-2fa@example.test');
        for ($i = 0; $i < 5; ++$i) {
            $this->submitCode($client, '000000');
        }

        // 6-digit codes can't be brute-forced: even the right one is rejected now
        $this->submitCode($client, self::currentTotpCode($secret));
        self::assertResponseRedirects('/2fa');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Too many');
    }

    public function testUndecryptableSecretFailsClosed(): void
    {
        $client = $this->createLoginClient();
        $user = $this->createUser('broken-2fa@example.test', 'Broken 2FA', 'password');
        $user->enableTwoFactor('JBSWY3DPEHPK3PXP', 'not-decryptable', []);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        // the bundle reads the secret right after the password: decrypting fails, the login aborts
        $this->submitLogin($client, 'broken-2fa@example.test');
        self::assertResponseStatusCodeSame(500);

        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testBackupCodeWorksOnlyOnce(): void
    {
        $client = $this->createLoginClient();
        $this->createTwoFactorUser('backup-2fa@example.test', ['abcde-fghjk', 'mnpqr-stuvw']);

        $this->submitLogin($client, 'backup-2fa@example.test');
        $this->submitCode($client, 'ABCDE FGHJK'); // case and separators don't matter
        self::assertResponseRedirects('/');

        $user = static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['email' => 'backup-2fa@example.test']);
        self::assertSame(1, $user?->getRemainingBackupCodeCount());

        $client->request('GET', '/logout');
        $this->submitLogin($client, 'backup-2fa@example.test');
        $this->submitCode($client, 'abcde-fghjk');
        self::assertResponseRedirects('/2fa');
    }

    public function testRememberMeCookieIsOnlySetAfterTheCode(): void
    {
        $client = $this->createLoginClient();
        $secret = $this->createTwoFactorUser('remember-2fa@example.test');

        $client->request('GET', '/login');
        $client->submitForm('Log in', ['_username' => 'remember-2fa@example.test', '_password' => 'password', '_remember_me' => true]);
        self::assertNull($client->getCookieJar()->get('REMEMBERME'));

        $this->submitCode($client, self::currentTotpCode($secret));
        $cookie = $client->getCookieJar()->get('REMEMBERME');
        self::assertNotNull($cookie);

        // the cookie alone logs in without asking for the code again
        $client->getCookieJar()->clear();
        $client->getCookieJar()->set($cookie);
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
    }

    private function createLoginClient(): KernelBrowser
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        // failed code attempts count against the login throttling, see SecurityControllerTest
        static::getContainer()->get('cache.rate_limiter')->clear();

        return $client;
    }

    private function submitLogin(KernelBrowser $client, string $email): void
    {
        $client->request('GET', '/login');
        $client->submitForm('Log in', ['_username' => $email, '_password' => 'password']);
    }

    private function submitCode(KernelBrowser $client, string $code): void
    {
        $client->request('GET', '/2fa');
        $client->submitForm('Verify', ['_auth_code' => $code]);
    }
}
