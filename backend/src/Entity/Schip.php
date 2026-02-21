<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SchipRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SchipRepository::class)]
#[ORM\Table(name: 'schip')]
#[ORM\HasLifecycleCallbacks]
class Schip
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200, unique: true)]
    private ?string $naam = null;

    #[ORM\Column(length: 220, unique: true, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Scheepsdata>
     */
    #[ORM\OneToMany(targetEntity: Scheepsdata::class, mappedBy: 'ship', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['receivedAt' => 'DESC'])]
    private Collection $scheepsdata;

    public function __construct()
    {
        $this->scheepsdata = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNaam(): ?string
    {
        return $this->naam;
    }

    public function setNaam(string $naam): static
    {
        $this->naam = $naam;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, Scheepsdata>
     */
    public function getScheepsdata(): Collection
    {
        return $this->scheepsdata;
    }

    public function addScheepsdata(Scheepsdata $scheepsdata): static
    {
        if (!$this->scheepsdata->contains($scheepsdata)) {
            $this->scheepsdata->add($scheepsdata);
            $scheepsdata->setShip($this);
        }
        return $this;
    }

    public function removeScheepsdata(Scheepsdata $scheepsdata): static
    {
        if ($this->scheepsdata->removeElement($scheepsdata)) {
            if ($scheepsdata->getShip() === $this) {
                $scheepsdata->setShip(null);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->naam ?? '';
    }
}
