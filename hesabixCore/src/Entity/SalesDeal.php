<?php

namespace App\Entity;

use App\Repository\SalesDealRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SalesDealRepository::class)]
class SalesDeal
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
    #[ORM\JoinColumn(nullable: false)]
    private ?SalesCenter $center = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Person $person = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'submitter_id', nullable: false)]
    private ?User $submitter = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'owner_id', nullable: true)]
    private ?User $owner = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'approved_by_id', nullable: true)]
    private ?User $approvedBy = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    // planning → visited → pre_invoice → approved → dispatched → invoiced → collected
    #[ORM\Column(length: 50)]
    private ?string $stage = 'planning';

    #[ORM\Column(type: Types::DECIMAL, precision: 30, scale: 0, nullable: true)]
    private ?string $amount = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $dueDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $preInvoiceId = null;

    #[ORM\Column(nullable: true)]
    private ?int $storeroomTicketId = null;

    #[ORM\Column(nullable: true)]
    private ?int $sellDocId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $approvedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $des = null;

    #[ORM\Column(length: 50)]
    private ?string $createdAt = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $closedAt = null;

    public function getId(): ?int { return $this->id; }

    public function getBid(): ?Business { return $this->bid; }
    public function setBid(?Business $bid): static { $this->bid = $bid; return $this; }

    public function getYear(): ?Year { return $this->year; }
    public function setYear(?Year $year): static { $this->year = $year; return $this; }

    public function getCenter(): ?SalesCenter { return $this->center; }
    public function setCenter(?SalesCenter $center): static { $this->center = $center; return $this; }

    public function getPerson(): ?Person { return $this->person; }
    public function setPerson(?Person $person): static { $this->person = $person; return $this; }

    public function getSubmitter(): ?User { return $this->submitter; }
    public function setSubmitter(?User $submitter): static { $this->submitter = $submitter; return $this; }

    public function getOwner(): ?User { return $this->owner; }
    public function setOwner(?User $owner): static { $this->owner = $owner; return $this; }

    public function getApprovedBy(): ?User { return $this->approvedBy; }
    public function setApprovedBy(?User $approvedBy): static { $this->approvedBy = $approvedBy; return $this; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getStage(): ?string { return $this->stage; }
    public function setStage(string $stage): static { $this->stage = $stage; return $this; }

    public function getAmount(): ?string { return $this->amount; }
    public function setAmount(?string $amount): static { $this->amount = $amount; return $this; }

    public function getDueDate(): ?string { return $this->dueDate; }
    public function setDueDate(?string $dueDate): static { $this->dueDate = $dueDate; return $this; }

    public function getPreInvoiceId(): ?int { return $this->preInvoiceId; }
    public function setPreInvoiceId(?int $preInvoiceId): static { $this->preInvoiceId = $preInvoiceId; return $this; }

    public function getStoreroomTicketId(): ?int { return $this->storeroomTicketId; }
    public function setStoreroomTicketId(?int $storeroomTicketId): static { $this->storeroomTicketId = $storeroomTicketId; return $this; }

    public function getSellDocId(): ?int { return $this->sellDocId; }
    public function setSellDocId(?int $sellDocId): static { $this->sellDocId = $sellDocId; return $this; }

    public function getApprovedAt(): ?string { return $this->approvedAt; }
    public function setApprovedAt(?string $approvedAt): static { $this->approvedAt = $approvedAt; return $this; }

    public function getDes(): ?string { return $this->des; }
    public function setDes(?string $des): static { $this->des = $des; return $this; }

    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function setCreatedAt(string $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getClosedAt(): ?string { return $this->closedAt; }
    public function setClosedAt(?string $closedAt): static { $this->closedAt = $closedAt; return $this; }
}
