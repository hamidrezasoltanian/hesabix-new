<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class DealActivity
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

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    // comment | status_change | task_done | created
    #[ORM\Column(length: 30)]
    private ?string $type = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(length: 50)]
    private ?string $date = null;

    public function getId(): ?int { return $this->id; }

    public function getDeal(): ?SalesDeal { return $this->deal; }
    public function setDeal(?SalesDeal $deal): static { $this->deal = $deal; return $this; }

    public function getBid(): ?Business { return $this->bid; }
    public function setBid(?Business $bid): static { $this->bid = $bid; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getType(): ?string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getContent(): ?string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getDate(): ?string { return $this->date; }
    public function setDate(string $date): static { $this->date = $date; return $this; }
}
