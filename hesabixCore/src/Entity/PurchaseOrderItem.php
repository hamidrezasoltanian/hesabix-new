<?php

namespace App\Entity;

use App\Repository\PurchaseOrderItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PurchaseOrderItemRepository::class)]
class PurchaseOrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PurchaseOrder $po = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commodity $commodity = null;

    #[ORM\Column(length: 50)]
    private ?string $qty = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $unitPrice = null;

    #[ORM\Column(length: 50)]
    private string $receivedQty = '0';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPo(): ?PurchaseOrder
    {
        return $this->po;
    }

    public function setPo(?PurchaseOrder $po): static
    {
        $this->po = $po;
        return $this;
    }

    public function getCommodity(): ?Commodity
    {
        return $this->commodity;
    }

    public function setCommodity(?Commodity $commodity): static
    {
        $this->commodity = $commodity;
        return $this;
    }

    public function getQty(): ?string
    {
        return $this->qty;
    }

    public function setQty(string $qty): static
    {
        $this->qty = $qty;
        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    public function getReceivedQty(): string
    {
        return $this->receivedQty;
    }

    public function setReceivedQty(string $receivedQty): static
    {
        $this->receivedQty = $receivedQty;
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
}
