<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Schip;
use App\Entity\Scheepsdata;
use App\Repository\SchipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api')]
final class StatusController extends AbstractController
{
    private const MAX_SHIP_NAME_LENGTH = 200;
    private const MAX_DEVICE_NAME_LENGTH = 255;

    public function __construct(
        private SchipRepository $schipRepository,
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
    ) {
    }

    #[Route('/status', name: 'api_status_post', methods: ['POST'])]
    public function postStatus(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        if ($raw === '' || $raw === false) {
            return $this->json(['ok' => false, 'error' => 'Lege body'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['ok' => false, 'error' => 'Ongeldige JSON: ' . json_last_error_msg()], Response::HTTP_BAD_REQUEST);
        }
        if (!is_array($data)) {
            return $this->json(['ok' => false, 'error' => 'Body moet een JSON-object zijn'], Response::HTTP_BAD_REQUEST);
        }
        if (!isset($data['ship']) || !is_string($data['ship'])) {
            return $this->json(['ok' => false, 'error' => 'Ontbrekend of ongeldig veld: ship'], Response::HTTP_BAD_REQUEST);
        }
        $devices = $data['devices'] ?? null;
        if (!is_array($devices)) {
            return $this->json(['ok' => false, 'error' => 'Ontbrekend of ongeldig veld: devices (moet een object/array zijn)'], Response::HTTP_BAD_REQUEST);
        }

        $shipName = trim($data['ship']);
        $shipName = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $shipName) ?? $shipName;
        if (mb_strlen($shipName) > self::MAX_SHIP_NAME_LENGTH) {
            $shipName = mb_substr($shipName, 0, self::MAX_SHIP_NAME_LENGTH);
        }
        if ($shipName === '') {
            return $this->json(['ok' => false, 'error' => 'Lege scheepsnaam'], Response::HTTP_BAD_REQUEST);
        }

        $devicesClean = [];
        foreach ($devices as $devName => $dev) {
            if (!is_array($dev)) {
                continue;
            }
            $key = is_string($devName) ? trim($devName) : (string) $devName;
            if ($key === '' || mb_strlen($key) > self::MAX_DEVICE_NAME_LENGTH) {
                continue;
            }
            $devicesClean[$key] = [
                'ip' => isset($dev['ip']) ? trim((string) $dev['ip']) : '',
                'status' => isset($dev['status']) ? trim((string) $dev['status']) : '',
            ];
        }

        $lastUpdate = isset($data['last_update']) && is_string($data['last_update'])
            ? trim($data['last_update'])
            : (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $receivedAt = $this->parseReceivedAt($lastUpdate);

        $shipPosition = null;
        if (isset($data['ship_position']) && (is_string($data['ship_position']) || is_numeric($data['ship_position']))) {
            $shipPosition = trim((string) $data['ship_position']);
            if ($shipPosition === '') {
                $shipPosition = null;
            }
        }

        $sourceIp = $this->getClientWanIp($request);

        $ship = $this->schipRepository->findByNaam($shipName);
        if ($ship === null) {
            $ship = new Schip();
            $ship->setNaam($shipName);
            $slug = strtolower((string) $this->slugger->slug($shipName));
            $ship->setSlug($slug === '' ? null : $slug);
        }

        $scheepsdata = new Scheepsdata();
        $scheepsdata->setShip($ship);
        $scheepsdata->setReceivedAt($receivedAt);
        $scheepsdata->setShipPosition($shipPosition);
        $scheepsdata->setSourceIp($sourceIp);
        $scheepsdata->setDevices($devicesClean);
        $ship->addScheepsdata($scheepsdata);

        $this->entityManager->persist($ship);
        $this->entityManager->persist($scheepsdata);
        $this->entityManager->flush();

        $response = ['ok' => true, 'ship' => $shipName];
        if ($sourceIp !== null) {
            $response['source_ip'] = $sourceIp;
        }
        return $this->json($response, Response::HTTP_OK);
    }

    private function parseReceivedAt(string $lastUpdate): \DateTimeImmutable
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $lastUpdate);
        if ($dt !== false) {
            return $dt;
        }
        $dt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $lastUpdate);
        if ($dt !== false) {
            return $dt;
        }
        return new \DateTimeImmutable();
    }

    private function getClientWanIp(Request $request): ?string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
        ];
        foreach ($headers as $h) {
            $val = $request->server->get($h);
            if ($val === null || $val === '') {
                continue;
            }
            $val = trim((string) $val);
            if ($val === '') {
                continue;
            }
            if (str_contains($val, ',')) {
                $val = trim(explode(',', $val)[0]);
            }
            if (filter_var($val, FILTER_VALIDATE_IP)) {
                return $val;
            }
        }
        $remote = $request->server->get('REMOTE_ADDR');
        return $remote !== null && filter_var($remote, FILTER_VALIDATE_IP) ? $remote : null;
    }
}
