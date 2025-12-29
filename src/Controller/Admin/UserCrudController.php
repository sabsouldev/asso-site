<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher) {}

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        $passwordField = TextField::new('password', 'Mot de passe')
            ->onlyOnForms()
            ->setFormType(PasswordType::class)
            ->setHelp('Saisir un mot de passe (il sera hashé). Laisser vide pour ne pas changer.');

        return [
            EmailField::new('email', 'Email'),
            TextField::new('Prenom', 'Prénom'),
            TextField::new('Nom', 'Nom'),

            ChoiceField::new('roles', 'Rôles')
                ->setChoices([
                    'Adhérent (ROLE_USER)' => 'ROLE_USER',
                    'Admin (ROLE_ADMIN)' => 'ROLE_ADMIN',
                ])
                ->allowMultipleChoices()
                ->renderExpanded(),

            $passwordField,
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            parent::persistEntity($entityManager, $entityInstance);
            return;
        }

        // Si aucun rôle choisi, on force ROLE_USER
        $roles = $entityInstance->getRoles();
        if (!in_array('ROLE_USER', $roles, true) && !in_array('ROLE_ADMIN', $roles, true)) {
            $entityInstance->setRoles(['ROLE_USER']);
        }

        // Hash du password (obligatoire à la création)
        $plain = $entityInstance->getPassword();
        if (!$plain) {
            throw new \RuntimeException('Mot de passe requis à la création.');
        }

        // Si ce n'est pas déjà un hash, on hash
        if (!str_starts_with($plain, '$2y$') && !str_starts_with($plain, '$argon2')) {
            $entityInstance->setPassword($this->hasher->hashPassword($entityInstance, $plain));
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            parent::updateEntity($entityManager, $entityInstance);
            return;
        }

        // En édition : si le champ password est vide → on ne change pas le mdp
        $plain = $entityInstance->getPassword();

        if ($plain && !str_starts_with($plain, '$2y$') && !str_starts_with($plain, '$argon2')) {
            $entityInstance->setPassword($this->hasher->hashPassword($entityInstance, $plain));
        } elseif (!$plain) {
            // On évite d'écraser le hash existant par null/"" :
            // EasyAdmin peut hydrater un champ vide -> on recharge l'ancien hash
            $uow = $entityManager->getUnitOfWork();
            $originalData = $uow->getOriginalEntityData($entityInstance);
            if (isset($originalData['password'])) {
                $entityInstance->setPassword($originalData['password']);
            }
        }

        parent::updateEntity($entityManager, $entityInstance);
    }
}