<?php

namespace App\Controller\Admin;

use App\Entity\Planning;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Vich\UploaderBundle\Form\Type\VichFileType;

class PlanningCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Planning::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Planning')
            ->setEntityLabelInPlural('Plannings')
            ->setDefaultSort(['updatedAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        $pdfUpload = Field::new('pdfFile', 'Planning (PDF)')
            ->onlyOnForms()
            ->setFormType(VichFileType::class)
            ->setFormTypeOptions([
                'required' => false,
                'allow_delete' => true,
                'download_uri' => false,
            ])
            ->setHelp('PDF uniquement.');

        return [
            TextField::new('titre', 'Titre'),
            TextField::new('pdfName', 'Fichier')->onlyOnIndex(),
            DateTimeField::new('updatedAt', 'Dernière mise à jour')->onlyOnIndex(),
            $pdfUpload,
        ];
    }
}
