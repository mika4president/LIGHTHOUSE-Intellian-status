<?php

namespace App\Controller\Admin;

use App\Entity\Scheepsdata;
use App\Repository\ScheepsdataRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ScheepsdataCrudController extends AbstractCrudController
{
    public function __construct(
        private ScheepsdataRepository $scheepsdataRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Scheepsdata::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Scheepsdata')
            ->setEntityLabelInPlural('Scheepsdata')
            ->setPageTitle('index', 'Scheepsdata – laatste status per schip')
            ->setDefaultSort(['ship' => 'ASC'])
            ->setPaginatorPageSize(50);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return $this->scheepsdataRepository->createLatestPerShipQueryBuilder();
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('ship', 'Schip');
        yield TextField::new('schotelsStatusSummary', 'Schotels (status)')->hideOnForm()->renderAsHtml();
        yield DateTimeField::new('createdAt', 'Aangemaakt')->hideOnForm();

        if ($pageName !== Crud::PAGE_INDEX) {
            yield DateTimeField::new('receivedAt', 'Ontvangen op')->hideOnIndex();
            yield TextField::new('shipPosition', 'Positie')->hideOnIndex();
            yield TextField::new('sourceIp', 'Bron-IP')->hideOnIndex();
        }
    }
}
