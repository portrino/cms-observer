<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Security\TotpSecretEncryptor;
use App\Tests\Functional\CreatesTwoFactorUserTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TwoFactorProfileTest extends WebTestCase
{
    use CreatesTwoFactorUserTrait;

    public function testSetupNeedsAValidCodeAndShowsBackupCodesOnce(): void
    {
        $client = $this->createProfileClient();
        $client->loginUser($this->createUser('setup-2fa@example.test', 'Setup', 'password'));

        $crawler = $client->request('GET', '/profile/2fa/enable');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('img[src^="data:image/svg+xml"]');
        $secret = str_replace(' ', '', $crawler->filter('code.user-select-all')->text());

        $client->submitForm('Enable two-factor authentication', ['two_factor_confirmation[code]' => '000000']);
        self::assertSelectorTextContains('.invalid-feedback', 'The code is not valid');

        // the secret survives the failed attempt, so the scanned app keeps working
        $client->submitForm('Enable two-factor authentication', ['two_factor_confirmation[code]' => self::currentTotpCode($secret)]);
        self::assertResponseRedirects('/profile/2fa/backup-codes');

        $crawler = $client->followRedirect();
        self::assertCount(10, $crawler->filter('#backup-codes li'));

        $client->request('GET', '/profile/2fa/backup-codes');
        self::assertResponseRedirects('/profile');

        $user = $this->reloadUser('setup-2fa@example.test');
        self::assertTrue($user->isTotpAuthenticationEnabled());
        self::assertSame(10, $user->getRemainingBackupCodeCount());
        self::assertStringNotContainsString($secret, (string) $user->getEncryptedTotpSecret());
    }

    public function testSetupIsNotOfferedWithoutAnEncryptionKey(): void
    {
        $client = $this->createProfileClient();
        $client->disableReboot(); // keeps the replaced encryptor for every request
        static::getContainer()->set(TotpSecretEncryptor::class, new TotpSecretEncryptor(''));
        $client->loginUser($this->createUser('no-key-2fa@example.test', 'No Key', 'password'));

        $client->request('GET', '/profile');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('code:contains("TOTP_ENCRYPTION_KEY")');
        self::assertSelectorNotExists('a[href="/profile/2fa/enable"]');

        // instead of a 500 once the first code is entered
        $client->request('GET', '/profile/2fa/enable');
        self::assertResponseRedirects('/profile');
    }

    public function testRegeneratingBackupCodesInvalidatesTheOldOnes(): void
    {
        $client = $this->createProfileClient();
        $secret = $this->createTwoFactorUser('regen-2fa@example.test');
        $client->loginUser($this->reloadUser('regen-2fa@example.test'));

        $client->request('GET', '/profile/2fa/backup-codes/regenerate');
        $client->submitForm('Generate new backup codes', ['two_factor_confirmation[code]' => self::currentTotpCode($secret)]);
        self::assertResponseRedirects('/profile/2fa/backup-codes');

        $newCode = $client->followRedirect()->filter('#backup-codes li')->first()->text();
        $user = $this->reloadUser('regen-2fa@example.test');
        self::assertFalse($user->isBackupCode('abcde-fghjk'));
        self::assertTrue($user->isBackupCode($newCode));
    }

    public function testCodeChecksAreThrottled(): void
    {
        $client = $this->createProfileClient();
        $secret = $this->createTwoFactorUser('throttled-profile@example.test');
        $client->loginUser($this->reloadUser('throttled-profile@example.test'));

        $client->request('GET', '/profile/2fa/backup-codes/regenerate');
        for ($i = 0; $i < 5; ++$i) {
            $client->submitForm('Generate new backup codes', ['two_factor_confirmation[code]' => '000000']);
        }

        // even the right code is rejected now, and nothing was regenerated
        $client->submitForm('Generate new backup codes', ['two_factor_confirmation[code]' => self::currentTotpCode($secret)]);
        self::assertResponseIsUnprocessable();
        self::assertSelectorTextContains('.invalid-feedback', 'Too many failed attempts');
        self::assertTrue($this->reloadUser('throttled-profile@example.test')->isBackupCode('abcde-fghjk'));
    }

    public function testDisablingNeedsThePasswordAndACode(): void
    {
        $client = $this->createProfileClient();
        $this->createTwoFactorUser('disable-2fa@example.test');
        $client->loginUser($this->reloadUser('disable-2fa@example.test'));

        $client->request('GET', '/profile/2fa/disable');
        $client->submitForm('Disable two-factor authentication', [
            'two_factor_confirmation[currentPassword]' => 'wrong-password',
            'two_factor_confirmation[code]' => 'abcde-fghjk',
        ]);
        self::assertSelectorTextContains('.invalid-feedback', 'Current password is incorrect.');
        self::assertTrue($this->reloadUser('disable-2fa@example.test')->isTotpAuthenticationEnabled());

        $client->submitForm('Disable two-factor authentication', [
            'two_factor_confirmation[currentPassword]' => 'password',
            'two_factor_confirmation[code]' => 'abcde-fghjk', // a backup code works too, e.g. with the phone lost
        ]);
        self::assertResponseRedirects('/profile');
        self::assertFalse($this->reloadUser('disable-2fa@example.test')->isTotpAuthenticationEnabled());
    }

    private function createProfileClient(): KernelBrowser
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        // limiter counters are keyed by user id, which can repeat after a test DB rebuild
        static::getContainer()->get('cache.rate_limiter')->clear();

        return $client;
    }

    private function reloadUser(string $email): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);

        return $user;
    }
}
