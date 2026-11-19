<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\CmsType;
use App\Entity\Organization;
use App\Entity\Site;
use App\Repository\OrganizationRepository;
use App\Repository\SiteRepository;
use App\Security\SiteTokenFactory;
use App\Tests\Functional\CreatesUserTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardSiteCrudTest extends WebTestCase
{
    use CreatesUserTrait;

    public function testCreatingASiteIssuesAPushToken(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('crud-create@example.test', 'Crud Create', 'password'));

        $client->request('GET', '/projects/new');
        $client->submitForm('Create', [
            'site[name]' => 'New Project',
            'site[url]' => 'https://new-project.test',
            'site[type]' => 'typo3',
        ]);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-warning', 'copy it now');

        $site = static::getContainer()->get(SiteRepository::class)->findOneBy(['name' => 'New Project']);
        self::assertNotNull($site);
    }

    public function testCreatingASiteWithANewOrganization(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('crud-new-org@example.test', 'Crud New Org', 'password'));

        $client->request('GET', '/projects/new');
        $client->submitForm('Create', [
            'site[name]' => 'Project With New Org',
            'site[url]' => 'https://new-org.test',
            'site[newOrganization]' => ' Brand New Agency ',
        ]);

        self::assertResponseRedirects();
        $site = static::getContainer()->get(SiteRepository::class)->findOneBy(['name' => 'Project With New Org']);
        self::assertSame('Brand New Agency', $site?->getOrganization()?->getName());
    }

    public function testANewOrganizationWithAnExistingNameReusesIt(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('crud-reuse-org@example.test', 'Crud Reuse Org', 'password'));
        $existing = new Organization('Existing Agency');
        $site = $this->createSite('Reuse Org Site');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($existing);
        $entityManager->flush();

        $client->request('GET', '/projects/'.$site->getId().'/edit');
        $client->submitForm('Save', ['site[newOrganization]' => 'existing agency']);

        self::assertResponseRedirects('/projects/'.$site->getId());
        $entityManager->clear();
        self::assertSame($existing->getId(), $entityManager->getRepository(Site::class)->find($site->getId())?->getOrganization()?->getId());
        self::assertCount(1, static::getContainer()->get(OrganizationRepository::class)->findBy(['name' => 'Existing Agency']));
    }

    public function testChoosingAnOrganizationAndEnteringANewOneIsRejected(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('crud-both-org@example.test', 'Crud Both Org', 'password'));
        $organization = new Organization('Chosen Agency');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($organization);
        $entityManager->flush();

        $client->request('GET', '/projects/new');
        $client->submitForm('Create', [
            'site[name]' => 'Project With Both',
            'site[url]' => 'https://both.test',
            'site[organization]' => (string) $organization->getId(),
            'site[newOrganization]' => 'Another Agency',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'not both');
        self::assertNull(static::getContainer()->get(SiteRepository::class)->findOneBy(['name' => 'Project With Both']));
    }

    public function testEditingASite(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('crud-edit@example.test', 'Crud Edit', 'password'));
        $site = $this->createSite('Old Name');

        $client->request('GET', '/projects/'.$site->getId().'/edit');
        $client->submitForm('Save', ['site[name]' => 'New Name']);

        self::assertResponseRedirects('/projects/'.$site->getId());

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        self::assertSame('New Name', $entityManager->getRepository(Site::class)->find($site->getId())?->getName());
    }

    public function testDeletingASiteRequiresTheExactConfirmationText(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('crud-delete@example.test', 'Crud Delete', 'password'));
        $site = $this->createSite('Delete Me');

        $crawler = $client->request('GET', '/projects/'.$site->getId().'/edit');
        $csrfToken = (string) $crawler->filter('form[action$="/delete"] input[name="_token"]')->attr('value');

        $client->request('POST', '/projects/'.$site->getId().'/delete', [
            'confirmation' => 'wrong confirmation',
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/projects/'.$site->getId().'/edit');
        self::assertNotNull(static::getContainer()->get(SiteRepository::class)->find($site->getId()));

        $client->request('POST', '/projects/'.$site->getId().'/delete', [
            'confirmation' => ' Delete Me ', // padded, like the trimming JS check accepts
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/');
        self::assertNull(static::getContainer()->get(SiteRepository::class)->find($site->getId()));
    }

    public function testRegeneratingTheTokenReplacesTheOldOne(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('crud-token@example.test', 'Crud Token', 'password'));
        $site = $this->createSite('Token Site');

        $crawler = $client->request('GET', '/projects/'.$site->getId().'/edit');
        $client->submit($crawler->filter('form[action$="/regenerate-token"]')->form());

        self::assertResponseRedirects('/projects/'.$site->getId());
        $crawler = $client->followRedirect();
        $token = $crawler->filter('.alert-warning code')->text();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        self::assertSame(
            static::getContainer()->get(SiteTokenFactory::class)->hash($token),
            $entityManager->getRepository(Site::class)->find($site->getId())?->getTokenHash(),
        );
    }

    public function testRegeneratingTheTokenNeedsAValidCsrfToken(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('crud-token-csrf@example.test', 'Crud Token Csrf', 'password'));
        $site = $this->createSite('Token Csrf Site');

        $client->request('POST', '/projects/'.$site->getId().'/regenerate-token', ['_token' => 'invalid']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(hash('sha256', 'Token Csrf Site'), static::getContainer()->get(SiteRepository::class)->find($site->getId())?->getTokenHash());
    }

    private function createSite(string $name): Site
    {
        $site = new Site($name, CmsType::Typo3, 'https://example.test', hash('sha256', $name));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($site);
        $entityManager->flush();

        return $site;
    }
}
