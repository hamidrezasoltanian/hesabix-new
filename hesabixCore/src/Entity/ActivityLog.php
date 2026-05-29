<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Business $bid = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Year $year = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?SalesCenter $center = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    // call / visit / sale / mission
    #[ORM\Column(length: 20)]
    private ?string $type = null;

    // تاریخ شمسی YYYY/MM/DD
    #[ORM\Column(length: 20)]
    private ?string $date = null;

    // مبلغ — فقط برای نوع sale
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $amount = null;

    // آیا فروش نقدی بود؟ — فقط برای نوع sale
    #[ORM\Column(options: ['default' => false])]
    private bool $cashSale = false;

    // آیا ماموریت انجام شد؟ — فقط برای نوع mission
    #[ORM\Column(options: ['default' => false])]
    private bool $done = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $createdAt = null;

    public function getId(): ?int { return $this->id; }

    public function getBid(): ?Business { return $this->bid; }
    public function setBid(?Business $bid): static { $this->bid = $bid; return $this; }

    public function getYear(): ?Year { return $this->year; }
    public function setYear(?Year $year): static { $this->year = $year; return $this; }

    public function getCenter(): ?SalesCenter { return $this->center; }
    public function setCenter(?SalesCenter $center): static { $this->center = $center; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getType(): ?string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getDate(): ?string { return $this->date; }
    public function setDate(string $date): static { $this->date = $date; return $this; }

    public function getAmount(): ?string { return $this->amount; }
    public function setAmount(?string $amount): static { $this->amount = $amount; return $this; }

    public function isCashSale(): bool { return $this->cashSale; }
    public function setCashSale(bool $cashSale): static { $this->cashSale = $cashSale; return $this; }

    public function isDone(): bool { return $this->done; }
    public function setDone(bool $done): static { $this->done = $done; return $this; }

    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $note): static { $this->note = $note; return $this; }

    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function setCreatedAt(?string $createdAt): static { $this->createdAt = $createdAt; return $this; }
}
