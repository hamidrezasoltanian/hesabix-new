<?php

namespace App\Entity;

use App\Repository\StoreroomCountItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StoreroomCountItemRepository::class)]
class StoreroomCountItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?StoreroomCount $countSession = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commodity $commodity = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lotNo = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $expiryDate = null;

    #[ORM\Column(length: 50)]
    private string $systemQty = '0';

    #[ORM\Column(length: 50)]
    private string $physicalQty = '0';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $note = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCountSession(): ?StoreroomCount
    {
        return $this->countSession;
    }

    public function setCountSession(?StoreroomCount $countSession): static
    {
        $this->countSession = $countSession;
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

    public function getLotNo(): ?string
    {
        return $this->lotNo;
    }

    public function setLotNo(?string $lotNo): static
    {
        $this->lotNo = $lotNo;
        return $this;
    }

    public function getExpiryDate(): ?string
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(?string $expiryDate): static
    {
        $this->expiryDate = $expiryDate;
        return $this;
    }

    public function getSystemQty(): string
    {
        return $this->systemQty;
    }

    public function setSystemQty(string $systemQty): static
    {
        $this->systemQty = $systemQty;
        return $this;
    }

    public function getPhysicalQty(): string
    {
        return $this->physicalQty;
    }

    public function setPhysicalQty(string $physicalQty): static
    {
        $this->physicalQty = $physicalQty;
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
}
