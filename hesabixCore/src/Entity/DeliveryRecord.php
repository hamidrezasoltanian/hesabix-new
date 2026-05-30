<?php

namespace App\Entity;

use App\Repository\DeliveryRecordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeliveryRecordRepository::class)]
class DeliveryRecord
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
    #[ORM\JoinColumn(nullable: true)]
    private ?StoreroomTicket $ticket = null;

    #[ORM\Column(length: 20)]
    private string $status = 'pending';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $courier = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $trackingCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $recipientName = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $recipientTel = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $sentAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $deliveredAt = null;

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

    public function getTicket(): ?StoreroomTicket
    {
        return $this->ticket;
    }

    public function setTicket(?StoreroomTicket $ticket): static
    {
        $this->ticket = $ticket;
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

    public function getCourier(): ?string
    {
        return $this->courier;
    }

    public function setCourier(?string $courier): static
    {
        $this->courier = $courier;
        return $this;
    }

    public function getTrackingCode(): ?string
    {
        return $this->trackingCode;
    }

    public function setTrackingCode(?string $trackingCode): static
    {
        $this->trackingCode = $trackingCode;
        return $this;
    }

    public function getRecipientName(): ?string
    {
        return $this->recipientName;
    }

    public function setRecipientName(?string $recipientName): static
    {
        $this->recipientName = $recipientName;
        return $this;
    }

    public function getRecipientTel(): ?string
    {
        return $this->recipientTel;
    }

    public function setRecipientTel(?string $recipientTel): static
    {
        $this->recipientTel = $recipientTel;
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

    public function getSentAt(): ?string
    {
        return $this->sentAt;
    }

    public function setSentAt(?string $sentAt): static
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getDeliveredAt(): ?string
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?string $deliveredAt): static
    {
        $this->deliveredAt = $deliveredAt;
        return $this;
    }
}
