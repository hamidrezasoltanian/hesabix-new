<?php

namespace App\Entity;

use App\Repository\StoreroomItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StoreroomItemRepository::class)]
class StoreroomItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'storeroomItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?StoreroomTicket $ticket = null;

    #[ORM\ManyToOne(inversedBy: 'storeroomItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commodity $commodity = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    private ?string $count = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $des = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $referal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lotNo = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $expiryDate = null;

    #[ORM\ManyToOne(inversedBy: 'storeroomItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Business $bid = null;

    #[ORM\ManyToOne(inversedBy: 'storeroomItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Storeroom $Storeroom = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicket(): ?StoreroomTicket
    {
        return $this->ticket;
    }

    public function setTicket(?StoreroomTicket $ticket): static
    {
        $this->ticket = $ticket;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCount(): ?string
    {
        return $this->count;
    }

    public function setCount(string $count): static
    {
        $this->count = $count;

        return $this;
    }

    public function getDes(): ?string
    {
        return $this->des;
    }

    public function setDes(?string $des): static
    {
        $this->des = $des;

        return $this;
    }

    public function getReferal(): ?string
    {
        return $this->referal;
    }

    public function setReferal(?string $referal): static
    {
        $this->referal = $referal;

        return $this;
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
        return $this->Storeroom;
    }

    public function setStoreroom(?Storeroom $Storeroom): static
    {
        $this->Storeroom = $Storeroom;

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

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $imedStatus = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $imedRef = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $salePrice = null;

    public function getImedStatus(): ?string
    {
        return $this->imedStatus;
    }

    public function setImedStatus(?string $imedStatus): static
    {
        $this->imedStatus = $imedStatus;
        return $this;
    }

    public function getImedRef(): ?string
    {
        return $this->imedRef;
    }

    public function setImedRef(?string $imedRef): static
    {
        $this->imedRef = $imedRef;
        return $this;
    }

    public function getSalePrice(): ?string
    {
        return $this->salePrice;
    }

    public function setSalePrice(?string $salePrice): static
    {
        $this->salePrice = $salePrice;
        return $this;
    }
}
