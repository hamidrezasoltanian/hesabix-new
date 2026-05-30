<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class DealTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SalesDeal $deal = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Business $bid = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column]
    private bool $done = false;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $doneAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $doneBy = null;

    #[ORM\Column]
    private int $displayOrder = 0;

    #[ORM\Column(length: 50)]
    private ?string $createdAt = null;

    public function getId(): ?int { return $this->id; }

    public function getDeal(): ?SalesDeal { return $this->deal; }
    public function setDeal(?SalesDeal $deal): static { $this->deal = $deal; return $this; }

    public function getBid(): ?Business { return $this->bid; }
    public function setBid(?Business $bid): static { $this->bid = $bid; return $this; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function isDone(): bool { return $this->done; }
    public function setDone(bool $done): static { $this->done = $done; return $this; }

    public function getDoneAt(): ?string { return $this->doneAt; }
    public function setDoneAt(?string $doneAt): static { $this->doneAt = $doneAt; return $this; }

    public function getDoneBy(): ?User { return $this->doneBy; }
    public function setDoneBy(?User $doneBy): static { $this->doneBy = $doneBy; return $this; }

    public function getDisplayOrder(): int { return $this->displayOrder; }
    public function setDisplayOrder(int $v): static { $this->displayOrder = $v; return $this; }

    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function setCreatedAt(string $v): static { $this->createdAt = $v; return $this; }
}
