<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

trait CreatesUserTrait
{
    private function createUser(string $email, string $name, string $password): User
    {
        $container = static::getContainer();
        $user = new User($email, $name);
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, $password));

        $container->get(EntityManagerInterface::class)->persist($user);
        $container->get(EntityManagerInterface::class)->flush();

        return $user;
    }
}
