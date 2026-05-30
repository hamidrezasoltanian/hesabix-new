<?php

namespace App\Entity;

use App\Repository\StoreroomCountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StoreroomCountRepository::class)]
class StoreroomCount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'storeroomCounts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Business $bid = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Storeroom $storeroom = null;

    #[ORM\Column(length: 20)]
    private ?string $date = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $closedDate = null;

    #[ORM\Column(length: 20)]
    private string $status = 'open';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\OneToMany(mappedBy: 'countSession', targetEntity: StoreroomCountItem::class, orphanRemoval: true)]
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

    public function getStoreroom(): ?Storeroom
    {
        return $this->storeroom;
    }

    public function setStoreroom(?Storeroom $storeroom): static
    {
        $this->storeroom = $storeroom;
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

    public function getClosedDate(): ?string
    {
        return $this->closedDate;
    }

    public function setClosedDate(?string $closedDate): static
    {
        $this->closedDate = $closedDate;
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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;
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

    /**
     * @return Collection<int, StoreroomCountItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(StoreroomCountItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setCountSession($this);
        }
        return $this;
    }

    public function removeItem(StoreroomCountItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getCountSession() === $this) {
                $item->setCountSession(null);
            }
        }
        return $this;
    }
}
