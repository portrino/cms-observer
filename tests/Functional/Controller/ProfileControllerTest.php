<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Tests\Functional\CreatesUserTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProfileControllerTest extends WebTestCase
{
    use CreatesUserTrait;

    public function testSavingProfileChangesPersistsThem(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $user = $this->createUser('profile-save@example.test', 'Original Name', 'current-password');
        $client->loginUser($user);

        $client->request('GET', '/profile');
        $client->submitForm('Save', [
            'profile[name]' => 'Updated Name',
            'profile[email]' => 'profile-save@example.test',
            'profile[currentPassword]' => 'current-password',
        ]);

        self::assertResponseRedirects('/profile');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Profile updated.');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = $entityManager->getRepository(User::class)->find($user->getId());

        self::assertNotNull($reloaded);
        self::assertSame('Updated Name', $reloaded->getName());
    }

    public function testSavingWithWrongCurrentPasswordIsRejected(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $user = $this->createUser('profile-wrongpw@example.test', 'Original Name', 'current-password');
        $client->loginUser($user);

        $client->request('GET', '/profile');
        $client->submitForm('Save', [
            'profile[name]' => 'Updated Name',
            'profile[email]' => 'profile-wrongpw@example.test',
            'profile[currentPassword]' => 'not-the-right-password',
        ]);

        self::assertResponseIsUnprocessable();
        self::assertSelectorTextContains('.invalid-feedback', 'Current password is incorrect.');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = $entityManager->getRepository(User::class)->find($user->getId());

        self::assertSame('Original Name', $reloaded?->getName());
    }
}
