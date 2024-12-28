<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UserService
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function createUser(
        string $username,
        string $password,
        string $type
    ): User {
        $user = new User();
        $user->setUsername($username);
        $user->setPassword(password_hash($password, PASSWORD_BCRYPT));
        $user->setType($type); // Définit le type (agent ou spv)

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function createAgentUser(string $username, string $password): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setPassword(password_hash($password, PASSWORD_BCRYPT));
        $user->setRoles(["ROLE_AGENT"]);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function createSpvUser(string $username, string $password): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setPassword(password_hash($password, PASSWORD_BCRYPT));
        $user->setRoles(["ROLE_SPV"]);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function assignRole(User $user, string $role): void
    {
        $roles = $user->getRoles();
        if (!in_array($role, $roles)) {
            $roles[] = $role;
            $user->setRoles($roles);
            $this->entityManager->flush();
        }
    }
}
