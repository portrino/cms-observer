<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\CmsType;
use App\Entity\Organization;
use App\Entity\Site;
use App\Entity\SiteEvent;
use App\Entity\SiteEventType;
use App\Entity\SiteReport;
use App\Tests\Functional\CreatesUserTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Translated pages show German texts and no raw translation key like "project.add".
 */
class GermanPagesTest extends WebTestCase
{
    use CreatesUserTrait;

    /** @return iterable<string, array{string, string}> */
    public static function pages(): iterable
    {
        yield 'projects' => ['/', 'Projekt hinzufügen'];
        yield 'new project' => ['/projects/new', 'Aktiver Wartungsvertrag (SLA)'];
        yield 'project' => ['/projects/{site}', 'Systeminformationen'];
        yield 'edit project' => ['/projects/{site}/edit', 'Neuen Token erzeugen'];
        yield 'history' => ['/projects/{site}/history', 'Die PHP-Version ist wieder sicher.'];
        yield 'packages' => ['/packages', 'Pakete aller Projekte'];
        yield 'organizations' => ['/organizations', 'Organisation hinzufügen'];
        yield 'organization' => ['/organizations/{organization}', 'Beschreibung'];
        yield 'edit organization' => ['/organizations/{organization}/edit', 'Organisation bearbeiten'];
        yield 'status' => ['/status', 'Die Testmail kommt in der Sprache dieser Seite.'];
        yield 'profile' => ['/profile', 'Automatisch (Browsersprache)'];
        yield 'settings' => ['/settings', 'Standard-Stundensatz (EUR)'];
        yield 'new organization' => ['/organizations/new', 'Alle Organisationen'];
    }

    #[DataProvider('pages')]
    public function testThePageIsGerman(string $path, string $text): void
    {
        $client = static::createClient([], ['HTTPS' => 'on', 'HTTP_ACCEPT_LANGUAGE' => 'de']);
        $client->loginUser($this->createUser('german@example.test', 'German', 'password'));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $organization = new Organization('German Org');
        $site = new Site('German Site', CmsType::Typo3, 'https://german.test', 'hash-german', organization: $organization);
        $site->recordReport('13.4.0', '8.3.0', '10.11.0');
        $entityManager->persist($organization);
        $entityManager->persist($site);
        $entityManager->persist(new SiteReport($site, '13.4.0', '8.3.0', '10.11.0', []));
        $resolved = new TranslatableMessage('event.detail.php_secure');
        $entityManager->persist(new SiteEvent($site, SiteEventType::BecameSecure, 'PHP 8.3.0', null, null, 'PHP version is secure again.', SiteEvent::messagesOf(null, $resolved)));
        $entityManager->flush();

        $client->request('GET', strtr($path, ['{site}' => $site->getId(), '{organization}' => $organization->getId()]));

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString($text, $html);
        self::assertDoesNotMatchRegularExpression('/\b(?:common|nav|project|package|badge|organization|history|event|calculation|status|sync|profile|settings|two_factor)\.[a-z_.-]+\b(?!\.)/', $html, 'untranslated key');
    }

    public function testEveryMessageIsValidIcu(): void
    {
        $translator = static::getContainer()->get(TranslatorInterface::class);
        self::assertInstanceOf(TranslatorBagInterface::class, $translator);
        foreach (['en', 'de'] as $locale) {
            foreach (array_keys($translator->getCatalogue($locale)->all('messages')) as $key) {
                // a syntax error throws; the parameters only need to exist
                $translator->trans($key, ['count' => 1, 'latest' => 'empty', 'version' => '1.0', 'label' => 'x', 'name' => 'x'], 'messages', $locale);
            }
        }
        $this->addToAssertionCount(1);
    }
}
