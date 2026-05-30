<?php

namespace App\Entity;

use App\Repository\StoreroomRecallRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StoreroomRecallRepository::class)]
class StoreroomRecall
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
    private ?Commodity $commodity = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lotNo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(length: 10)]
    private string $priority = 'medium';

    #[ORM\Column(length: 20)]
    private string $status = 'active';

    #[ORM\Column(length: 20)]
    private ?string $startDate = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $endDate = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $affectedCount = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

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

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): static
    {
        $this->priority = $priority;
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

    public function getStartDate(): ?string
    {
        return $this->startDate;
    }

    public function setStartDate(string $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?string
    {
        return $this->endDate;
    }

    public function setEndDate(?string $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getAffectedCount(): ?string
    {
        return $this->affectedCount;
    }

    public function setAffectedCount(?string $affectedCount): static
    {
        $this->affectedCount = $affectedCount;
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
