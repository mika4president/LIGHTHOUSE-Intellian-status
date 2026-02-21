<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Scheepsdata;
use App\Entity\Schip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Scheepsdata>
 */
class ScheepsdataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Scheepsdata::class);
    }

    /**
     * Latest Scheepsdata per ship (one row per ship, the most recent receivedAt).
     *
     * @return array<int, Scheepsdata> Ship id => latest Scheepsdata
     */
    public function findLatestPerShip(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT sd.id
            FROM scheepsdata sd
            INNER JOIN (
                SELECT ship_id, MAX(received_at) AS max_received
                FROM scheepsdata
                GROUP BY ship_id
            ) latest ON sd.ship_id = latest.ship_id AND sd.received_at = latest.max_received
            ORDER BY sd.ship_id
        ';
        $result = $conn->executeQuery($sql);
        $ids = $result->fetchFirstColumn();
        if ($ids === []) {
            return [];
        }
        $qb = $this->createQueryBuilder('sd')
            ->andWhere('sd.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('sd.receivedAt', 'DESC');
        $items = $qb->getQuery()->getResult();
        $byShip = [];
        foreach ($items as $sd) {
            $ship = $sd->getShip();
            if ($ship !== null) {
                $byShip[$ship->getId()] = $sd;
            }
        }
        return $byShip;
    }

    /**
     * QueryBuilder voor de index: alleen de meest recente Scheepsdata per schip.
     */
    public function createLatestPerShipQueryBuilder(): QueryBuilder
    {
        $latest = $this->findLatestPerShip();
        $ids = array_map(static fn (Scheepsdata $sd) => $sd->getId(), $latest);
        $qb = $this->createQueryBuilder('sd')
            ->innerJoin('sd.ship', 's')
            ->addSelect('s');
        if ($ids === []) {
            $qb->andWhere('1 = 0');
            return $qb;
        }
        return $qb
            ->andWhere('sd.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('s.naam', 'ASC');
    }

    /**
     * All ships with their latest Scheepsdata (for dashboard).
     *
     * @return list<array{ship: Schip, latest: Scheepsdata}>
     */
    public function getShipsWithLatestData(): array
    {
        $latestPerShip = $this->findLatestPerShip();
        if ($latestPerShip === []) {
            return [];
        }
        $shipIds = array_keys($latestPerShip);
        $shipRepo = $this->getEntityManager()->getRepository(Schip::class);
        $ships = $shipRepo->createQueryBuilder('s')
            ->where('s.id IN (:ids)')
            ->setParameter('ids', $shipIds)
            ->orderBy('s.naam', 'ASC')
            ->getQuery()
            ->getResult();
        $out = [];
        foreach ($ships as $ship) {
            $id = $ship->getId();
            if (isset($latestPerShip[$id])) {
                $out[] = ['ship' => $ship, 'latest' => $latestPerShip[$id]];
            }
        }
        return $out;
    }

    public function countReceivedSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('sd')
            ->select('COUNT(sd.id)')
            ->where('sd.receivedAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Meest recente scheepsdata (voor admin dashboard).
     *
     * @return list<Scheepsdata>
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('sd')
            ->innerJoin('sd.ship', 's')
            ->addSelect('s')
            ->orderBy('sd.receivedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countTrackingNow(): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "
            SELECT sd.devices
            FROM scheepsdata sd
            INNER JOIN (
                SELECT ship_id, MAX(received_at) AS max_received
                FROM scheepsdata
                GROUP BY ship_id
            ) latest ON sd.ship_id = latest.ship_id AND sd.received_at = latest.max_received
        ";
        $result = $conn->executeQuery($sql);
        $count = 0;
        while ($row = $result->fetchAssociative()) {
            $devices = json_decode($row['devices'] ?? '{}', true);
            if (!is_array($devices)) {
                continue;
            }
            foreach ($devices as $d) {
                $status = is_array($d) ? ($d['status'] ?? '') : '';
                if (stripos($status, 'TRACKING') !== false) {
                    $count++;
                }
            }
        }
        return $count;
    }
}
