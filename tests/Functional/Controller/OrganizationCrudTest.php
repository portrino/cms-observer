<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Organization;
use App\Repository\OrganizationRepository;
use App\Tests\Functional\CreatesUserTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OrganizationCrudTest extends WebTestCase
{
    use CreatesUserTrait;

    public function testCreatingAnOrganization(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('org-create@example.test', 'Org Create', 'password'));

        $client->request('GET', '/organizations/new');
        $client->submitForm('Create', ['organization[name]' => 'Acme']);

        self::assertResponseRedirects();
        self::assertNotNull(static::getContainer()->get(OrganizationRepository::class)->findOneBy(['name' => 'Acme']));
    }

    public function testEditingAnOrganization(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('org-edit@example.test', 'Org Edit', 'password'));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $organization = new Organization('Old Name');
        $entityManager->persist($organization);
        $entityManager->flush();

        $client->request('GET', '/organizations/'.$organization->getId().'/edit');
        $client->submitForm('Save', ['organization[name]' => 'New Name']);

        self::assertResponseRedirects('/organizations/'.$organization->getId());

        $entityManager->clear();
        self::assertSame('New Name', $entityManager->getRepository(Organization::class)->find($organization->getId())?->getName());
    }
}
