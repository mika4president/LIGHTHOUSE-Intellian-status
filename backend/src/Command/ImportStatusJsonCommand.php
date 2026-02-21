<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Schip;
use App\Entity\Scheepsdata;
use App\Repository\SchipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:import-status-json',
    description: 'Import existing data/status.json into Schip and Scheepsdata (one record per ship).',
)]
final class ImportStatusJsonCommand extends Command
{
    public function __construct(
        private SchipRepository $schipRepository,
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'file',
            InputArgument::OPTIONAL,
            'Path to status.json (default: project root ../data/status.json)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');
        if ($file === null || $file === '') {
            $file = $this->projectDir . '/../data/status.json';
        }
        if (!is_file($file)) {
            $io->error('File not found: ' . $file);
            return Command::FAILURE;
        }
        $content = file_get_contents($file);
        if ($content === false) {
            $io->error('Could not read file.');
            return Command::FAILURE;
        }
        $data = json_decode($content, true);
        if (!is_array($data)) {
            $io->error('Invalid JSON or not an object.');
            return Command::FAILURE;
        }

        $imported = 0;
        foreach ($data as $shipName => $row) {
            if (!is_array($row)) {
                continue;
            }
            $shipName = trim((string) $shipName);
            if ($shipName === '') {
                continue;
            }
            if (mb_strlen($shipName) > 200) {
                $shipName = mb_substr($shipName, 0, 200);
            }
            $ship = $this->schipRepository->findByNaam($shipName);
            if ($ship === null) {
                $ship = new Schip();
                $ship->setNaam($shipName);
                $slug = strtolower((string) $this->slugger->slug($shipName));
                $ship->setSlug($slug === '' ? null : $slug);
                $this->entityManager->persist($ship);
            }

            $lastUpdate = $row['last_update'] ?? null;
            $receivedAt = new \DateTimeImmutable();
            if (is_string($lastUpdate)) {
                $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', trim($lastUpdate));
                if ($parsed !== false) {
                    $receivedAt = $parsed;
                }
            }
            $shipPosition = isset($row['ship_position']) && (is_string($row['ship_position']) || is_numeric($row['ship_position']))
                ? trim((string) $row['ship_position'])
                : null;
            if ($shipPosition === '') {
                $shipPosition = null;
            }
            $sourceIp = isset($row['source_ip']) && is_string($row['source_ip']) ? trim($row['source_ip']) : null;
            if ($sourceIp === '') {
                $sourceIp = null;
            }
            $devices = $row['devices'] ?? [];
            if (!is_array($devices)) {
                $devices = [];
            }
            $devicesClean = [];
            foreach ($devices as $devName => $dev) {
                if (!is_array($dev)) {
                    continue;
                }
                $key = is_string($devName) ? trim($devName) : (string) $devName;
                if ($key === '' || mb_strlen($key) > 255) {
                    continue;
                }
                $devicesClean[$key] = [
                    'ip' => isset($dev['ip']) ? trim((string) $dev['ip']) : '',
                    'status' => isset($dev['status']) ? trim((string) $dev['status']) : '',
                ];
            }

            $scheepsdata = new Scheepsdata();
            $scheepsdata->setShip($ship);
            $scheepsdata->setReceivedAt($receivedAt);
            $scheepsdata->setShipPosition($shipPosition);
            $scheepsdata->setSourceIp($sourceIp);
            $scheepsdata->setDevices($devicesClean);
            $ship->addScheepsdata($scheepsdata);
            $this->entityManager->persist($scheepsdata);
            $imported++;
        }

        $this->entityManager->flush();
        $io->success('Imported ' . $imported . ' ship(s) from ' . $file . '.');
        return Command::SUCCESS;
    }
}
