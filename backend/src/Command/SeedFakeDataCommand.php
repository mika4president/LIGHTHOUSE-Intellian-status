<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Schip;
use App\Entity\Scheepsdata;
use App\Repository\SchipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:seed-fake-data',
    description: 'Genereer fake schepen en scheepsdata voor het dashboard.',
)]
final class SeedFakeDataCommand extends Command
{
    private const SHIPS = [
        [
            'naam' => 'AmaStella',
            'positions' => ['51.92,4.48', '52.10,4.25', '51.85,4.62'], // Rotterdam / Noordzee
            'devices' => [
                'Astra 1' => ['ip' => '10.15.2.180', 'status' => 'TRACKING (LOCKED)'],
                'T12 Anuvu' => ['ip' => '10.15.2.181', 'status' => 'TRACKING (LOCKED)'],
            ],
        ],
        [
            'naam' => 'MV Example',
            'positions' => ['52.37,4.89', '52.35,4.92'], // Amsterdam
            'devices' => [
                'Dish_1' => ['ip' => '10.15.2.180', 'status' => 'TRACKING (LOCKED)'],
                'Dish_2' => ['ip' => '10.15.2.181', 'status' => 'IDLE / UNLOCK'],
            ],
        ],
        [
            'naam' => 'MS Rotterdam',
            'positions' => ['43.73,7.42'], // Monaco / Middellandse Zee
            'devices' => [
                'Schotel port' => ['ip' => '10.20.1.10', 'status' => 'TRACKING (LOCKED)'],
                'Schotel stuurboord' => ['ip' => '10.20.1.11', 'status' => 'SEARCHING'],
            ],
        ],
        [
            'naam' => 'Holland America Nieuw Amsterdam',
            'positions' => ['25.78,-80.18'], // Miami
            'devices' => [
                'VSAT 1' => ['ip' => '10.30.2.50', 'status' => 'TRACKING (LOCKED)'],
                'VSAT 2' => ['ip' => '10.30.2.51', 'status' => 'WRAPPING (CABLE)'],
            ],
        ],
        [
            'naam' => 'River Duchess',
            'positions' => ['48.21,16.38'], // Wenen (Donau)
            'devices' => [
                'Intellian S1' => ['ip' => '10.10.1.100', 'status' => 'OFFLINE'],
            ],
        ],
    ];

    public function __construct(
        private SchipRepository $schipRepository,
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('fresh', 'f', InputOption::VALUE_NONE, 'Verwijder eerst alle bestaande schepen en data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('fresh')) {
            $conn = $this->entityManager->getConnection();
            $conn->executeStatement('DELETE FROM scheepsdata');
            $conn->executeStatement('DELETE FROM schip');
            $this->entityManager->clear();
            $io->writeln('Bestande data verwijderd.');
        }

        $now = new \DateTimeImmutable();
        $created = 0;

        foreach (self::SHIPS as $config) {
            $ship = $this->schipRepository->findByNaam($config['naam']);
            if ($ship === null) {
                $ship = new Schip();
                $ship->setNaam($config['naam']);
                $slug = strtolower((string) $this->slugger->slug($config['naam']));
                $ship->setSlug($slug === '' ? null : $slug);
                $this->entityManager->persist($ship);
            }

            $positions = $config['positions'];
            $devices = $config['devices'];
            $baseMinutesAgo = [0, 8, 45, 120]; // laatste push nu, 8 min, 45 min, 2u geleden

            $shipIndex = array_search($config, self::SHIPS, true);
            $fakeIp = $shipIndex !== false ? '203.0.113.' . (10 + $shipIndex) : null;

            for ($i = 0; $i < count($positions); $i++) {
                $receivedAt = $now->modify('-' . ($baseMinutesAgo[$i] ?? 60) . ' minutes');
                $scheepsdata = new Scheepsdata();
                $scheepsdata->setShip($ship);
                $scheepsdata->setReceivedAt($receivedAt);
                $scheepsdata->setShipPosition($positions[$i]);
                $scheepsdata->setSourceIp($i === 0 ? $fakeIp : null);
                $scheepsdata->setDevices($devices);
                $ship->addScheepsdata($scheepsdata);
                $this->entityManager->persist($scheepsdata);
                $created++;
            }
        }

        $this->entityManager->flush();
        $io->success('Fake data aangemaakt: ' . count(self::SHIPS) . ' schepen, ' . $created . ' scheepsdata-records. Open het dashboard op /');
        return Command::SUCCESS;
    }
}
