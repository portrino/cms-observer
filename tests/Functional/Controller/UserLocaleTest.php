<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Tests\Functional\CreatesUserTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserLocaleTest extends WebTestCase
{
    use CreatesUserTrait;

    public function testTheBrowserLanguageIsTheDefault(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on', 'HTTP_ACCEPT_LANGUAGE' => 'de-DE,de;q=0.9,en;q=0.8']);
        $client->request('GET', '/login');
        self::assertSelectorExists('html[lang="de"]');

        $client->request('GET', '/login', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9']);
        self::assertSelectorExists('html[lang="en"]', 'a language without translations falls back to English');
    }

    public function testTheProfileLanguageWinsOverTheBrowser(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on', 'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9']);
        $client->loginUser($this->createUser('locale@example.test', 'Locale', 'password'));

        $crawler = $client->request('GET', '/profile');
        self::assertSelectorExists('html[lang="en"]');
        $client->submit($crawler->filter('#language-form')->form(['language[locale]' => 'de']));
        self::assertResponseRedirects('/profile');

        $client->followRedirect();
        self::assertSelectorExists('html[lang="de"]');
        $user = static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['email' => 'locale@example.test']);
        self::assertSame('de', $user?->getLocale());

        $crawler = $client->request('GET', '/profile');
        $client->submit($crawler->filter('#language-form')->form(['language[locale]' => '']));
        $client->followRedirect();
        self::assertSelectorExists('html[lang="en"]', 'automatic again: the browser language');
    }
}
