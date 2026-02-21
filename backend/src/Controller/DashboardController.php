<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ScheepsdataRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(
        private ScheepsdataRepository $scheepsdataRepository,
    ) {
    }

    #[Route('/', name: 'app_dashboard_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('q', '');
        $searchTrimmed = is_string($search) ? trim($search) : '';

        $shipsWithLatest = $this->scheepsdataRepository->getShipsWithLatestData();
        $onlineSince = (new \DateTimeImmutable())->modify('-15 minutes');
        $enriched = [];
        foreach ($shipsWithLatest as $row) {
            $received = $row['latest']->getReceivedAt();
            $position = $row['latest']->getShipPosition();
            $coords = null;
            if ($position !== null && $position !== '' && stripos($position, 'N/A') === false) {
                $parts = array_map('trim', explode(',', $position));
                if (count($parts) >= 2) {
                    $lat = filter_var($parts[0], FILTER_VALIDATE_FLOAT);
                    $lon = filter_var($parts[1], FILTER_VALIDATE_FLOAT);
                    if ($lat !== false && $lon !== false && $lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180) {
                        $coords = ['lat' => (float) $lat, 'lon' => (float) $lon];
                    }
                }
            }
            $age = null;
            if ($received !== null) {
                $now = new \DateTimeImmutable();
                $age = (int) $now->diff($received)->format('%i');
            }
            $enriched[] = [
                'ship' => $row['ship'],
                'latest' => $row['latest'],
                'coords' => $coords,
                'age' => $age,
            ];
        }
        $countOnline = 0;
        foreach ($enriched as $row) {
            $received = $row['latest']->getReceivedAt();
            if ($received && $received >= $onlineSince) {
                $countOnline++;
            }
        }
        $countTracking = $this->scheepsdataRepository->countTrackingNow();
        $countPushesToday = $this->scheepsdataRepository->countReceivedSince((new \DateTimeImmutable())->setTime(0, 0, 0));

        return $this->render('dashboard/index.html.twig', [
            'shipsWithLatest' => $enriched,
            'countShips' => count($enriched),
            'countOnline' => $countOnline,
            'countTracking' => $countTracking,
            'countPushesToday' => $countPushesToday,
            'search' => $searchTrimmed,
        ]);
    }
}
