<?php
// src/Command/CreateUserCommand.php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

#[AsCommand(name: "app:create-user")]
class CreateUserCommand extends Command
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $helper = $this->getHelper("question");
        $usernameQuestion = new Question("Enter the username: ");
        $passwordQuestion = new Question("Enter the password: ");
        $typeQuestion = new Question("Enter the type (agent or spv): "); // Demande le type sous forme de chaîne

        $username = $helper->ask($input, $output, $usernameQuestion);
        $password = $helper->ask($input, $output, $passwordQuestion);
        $type = $helper->ask($input, $output, $typeQuestion); // Récupère le type sous forme de chaîne

        if (!in_array($type, ["agent", "spv"])) {
            $output->writeln(
                "<error>Invalid type. Please enter 'agent' or 'spv'.</error>"
            );
            return Command::FAILURE;
        }

        $user = new User();
        $user->setUsername($username);
        $user->setPassword(password_hash($password, PASSWORD_BCRYPT));
        $user->setType($type); // Passe une chaîne ("agent" ou "spv") à setType

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $output->writeln("User $username created with type $type.");

        return Command::SUCCESS;
    }
}
