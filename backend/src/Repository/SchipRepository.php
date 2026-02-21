<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Schip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Schip>
 */
class SchipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Schip::class);
    }

    public function findByNaam(string $naam): ?Schip
    {
        return $this->findOneBy(['naam' => $naam], []);
    }

    public function findAllOrderedByNaam(): array
    {
        return $this->findBy([], ['naam' => 'ASC']);
    }
}
