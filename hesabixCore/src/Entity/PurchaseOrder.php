<?php

namespace App\Entity;

use App\Repository\PurchaseOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PurchaseOrderRepository::class)]
class PurchaseOrder
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

    #[ORM\Column(length: 50)]
    private ?string $code = null;

    #[ORM\Column(length: 20)]
    private ?string $date = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Person $person = null;

    #[ORM\Column(length: 20)]
    private string $status = 'draft';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $approvedBy = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $totalAmount = null;

    #[ORM\OneToMany(mappedBy: 'po', targetEntity: PurchaseOrderItem::class, orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBid(): ?Business
    {
        return $this->bid;
    }

    public function setBid(?Business $bid): static
    {
        $this->bid = $bid;
        return $this;
    }

    public function getYear(): ?Year
    {
        return $this->year;
    }

    public function setYear(?Year $year): static
    {
        $this->year = $year;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getDate(): ?string
    {
        return $this->date;
    }

    public function setDate(string $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getPerson(): ?Person
    {
        return $this->person;
    }

    public function setPerson(?Person $person): static
    {
        $this->person = $person;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?User $approvedBy): static
    {
        $this->approvedBy = $approvedBy;
        return $this;
    }

    public function getTotalAmount(): ?string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(?string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    /**
     * @return Collection<int, PurchaseOrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(PurchaseOrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setPo($this);
        }
        return $this;
    }

    public function removeItem(PurchaseOrderItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getPo() === $this) {
                $item->setPo(null);
            }
        }
        return $this;
    }
}
