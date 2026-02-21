<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ScheepsdataRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ScheepsdataRepository::class)]
#[ORM\Table(name: 'scheepsdata')]
#[ORM\Index(columns: ['ship_id', 'received_at'], name: 'idx_ship_received')]
#[ORM\Index(columns: ['received_at'], name: 'idx_received_at')]
#[ORM\HasLifecycleCallbacks]
class Scheepsdata
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'scheepsdata')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Schip $ship = null;

    #[ORM\Column(name: 'received_at')]
    private ?\DateTimeImmutable $receivedAt = null;

    #[ORM\Column(name: 'ship_position', length: 255, nullable: true)]
    private ?string $shipPosition = null;

    #[ORM\Column(name: 'source_ip', length: 45, nullable: true)]
    private ?string $sourceIp = null;

    /** @var array<string, array{ip?: string, status?: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $devices = [];

    #[ORM\Column(name: 'created_at')]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShip(): ?Schip
    {
        return $this->ship;
    }

    public function setShip(?Schip $ship): static
    {
        $this->ship = $ship;
        return $this;
    }

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(\DateTimeImmutable $receivedAt): static
    {
        $this->receivedAt = $receivedAt;
        return $this;
    }

    public function getShipPosition(): ?string
    {
        return $this->shipPosition;
    }

    public function setShipPosition(?string $shipPosition): static
    {
        $this->shipPosition = $shipPosition;
        return $this;
    }

    public function getSourceIp(): ?string
    {
        return $this->sourceIp;
    }

    public function setSourceIp(?string $sourceIp): static
    {
        $this->sourceIp = $sourceIp;
        return $this;
    }

    /**
     * @return array<string, array{ip?: string, status?: string}>
     */
    public function getDevices(): array
    {
        return $this->devices;
    }

    /**
     * @param array<string, array{ip?: string, status?: string}> $devices
     */
    public function setDevices(array $devices): static
    {
        $this->devices = $devices;
        return $this;
    }

    /**
     * Schotelstatus als HTML met Bootstrap-badges (voor EasyAdmin-lijst).
     * Groen = TRACKING, oranje = SEARCHING/WRAPPING, grijs = IDLE/UNLOCK, rood = OFFLINE/ERROR.
     */
    public function getSchotelsStatusSummary(): string
    {
        if ($this->devices === []) {
            return '';
        }
        $badges = [];
        foreach ($this->devices as $name => $dev) {
            $status = is_array($dev) ? ($dev['status'] ?? '') : '';
            $badgeClass = $this->statusToBadgeClass($status);
            $safeName = htmlspecialchars($name, \ENT_QUOTES, 'UTF-8');
            $badges[] = '<span class="badge ' . $badgeClass . '">' . $safeName . '</span>';
        }
        return implode(' ', $badges);
    }

    private function statusToBadgeClass(string $status): string
    {
        $statusUpper = strtoupper($status);
        if (str_contains($statusUpper, 'TRACKING')) {
            return 'bg-success';
        }
        if (str_contains($statusUpper, 'IDLE') || str_contains($statusUpper, 'UNLOCK')) {
            return 'bg-secondary';
        }
        if (str_contains($statusUpper, 'SEARCHING') || str_contains($statusUpper, 'WRAPPING')) {
            return 'bg-warning text-dark';
        }
        return 'bg-danger';
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
