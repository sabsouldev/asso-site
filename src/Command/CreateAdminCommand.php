<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée (ou met à jour) un compte administrateur.',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher
        )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l’admin')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe (sera hashé)')
            ->addArgument('firstname', InputArgument::OPTIONAL, 'Prénom', 'Admin')
            ->addArgument('lastname', InputArgument::OPTIONAL, 'Nom', 'Principal')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
       $email = (string) $input->getArgument('email');
        $plainPassword = (string) $input->getArgument('password');
        $firstname = (string) $input->getArgument('firstname');
        $lastname = (string) $input->getArgument('lastname');

        $repo = $this->em->getRepository(User::class);

        /** @var User|null $user */
        $user = $repo->findOneBy(['email' => $email]);

        $isNew = false;
        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $isNew = true;
        }

        // Mets à jour l’identité (utile même si l’utilisateur existe déjà)
        $user->setNom($firstname);
        $user->setPrénom($lastname);

        // Assure ROLE_ADMIN (et garde les rôles existants)
        $roles = $user->getRoles();
        if (!in_array('ROLE_ADMIN', $roles, true)) {
            $roles[] = 'ROLE_ADMIN';
        }
        $user->setRoles(array_values(array_unique($roles)));

        // Hash du mot de passe
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));

        if ($isNew) {
            $this->em->persist($user);
        }

        $this->em->flush();

        $output->writeln('');
        $output->writeln($isNew
            ? sprintf('✅ Admin créé : %s', $email)
            : sprintf('✅ Admin mis à jour : %s', $email)
        );

        return Command::SUCCESS;
    }
}
