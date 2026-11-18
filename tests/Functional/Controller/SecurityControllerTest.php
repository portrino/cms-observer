<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\CreatesUserTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    use CreatesUserTrait;

    /**
     * Failed logins in earlier tests or test runs must not throttle this one: the per-IP limit
     * (5 x max_attempts) survives successful logins and lives in the filesystem cache.
     */
    private function createLoginClient(): KernelBrowser
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        static::getContainer()->get('cache.rate_limiter')->clear();

        return $client;
    }

    public function testAnonymousAccessToTheDashboardRedirectsToLogin(): void
    {
        $client = $this->createLoginClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testLoginWithValidCredentialsReachesTheDashboard(): void
    {
        $client = $this->createLoginClient();
        $this->createUser('login-valid@example.test', 'Login Valid', 'correct-password');

        $client->request('GET', '/login');
        $client->submitForm('Log in', [
            '_username' => 'login-valid@example.test',
            '_password' => 'correct-password',
        ]);

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Projects');
    }

    public function testLoginIsThrottledAfterFiveFailedAttempts(): void
    {
        $client = $this->createLoginClient();
        $this->createUser('login-throttled@example.test', 'Login Throttled', 'correct-password');

        for ($i = 0; $i < 5; ++$i) {
            $client->request('GET', '/login');
            $client->submitForm('Log in', ['_username' => 'login-throttled@example.test', '_password' => 'wrong-password']);
        }

        // even the right password is rejected now
        $client->request('GET', '/login');
        $client->submitForm('Log in', ['_username' => 'login-throttled@example.test', '_password' => 'correct-password']);

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Too many failed login attempts');
    }

    public function testLoginWithWrongPasswordStaysOnTheLoginPage(): void
    {
        $client = $this->createLoginClient();
        $this->createUser('login-invalid@example.test', 'Login Invalid', 'correct-password');

        $client->request('GET', '/login');
        $client->submitForm('Log in', [
            '_username' => 'login-invalid@example.test',
            '_password' => 'wrong-password',
        ]);

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }
}
