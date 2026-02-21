<?php

namespace App\Controller\Admin;

use App\Entity\Schip;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class SchipCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Schip::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Schip')
            ->setEntityLabelInPlural('Schepen')
            ->setDefaultSort(['naam' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('naam', 'Naam');
        yield TextField::new('slug', 'Slug')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Aangemaakt')->hideOnForm();
        yield DateTimeField::new('updatedAt', 'Bijgewerkt')->hideOnForm();
    }
}
