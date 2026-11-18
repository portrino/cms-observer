<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\CmsType;
use App\Entity\Site;
use App\Repository\SiteRepository;
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
        $csrfToken = (string) $crawler->filter('input[name="_token"]')->attr('value');

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

    private function createSite(string $name): Site
    {
        $site = new Site($name, CmsType::Typo3, 'https://example.test', hash('sha256', $name));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($site);
        $entityManager->flush();

        return $site;
    }
}
