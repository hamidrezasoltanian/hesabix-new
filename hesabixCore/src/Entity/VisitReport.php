<?php

namespace App\Entity;

use App\Repository\VisitReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VisitReportRepository::class)]
class VisitReport
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
    private ?SalesCenter $center = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?SalesDeal $deal = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?WeekPlan $weekPlan = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $submitter = null;

    #[ORM\Column(length: 50)]
    private ?string $date = null;

    // interested / not_interested / follow_up / deal
    #[ORM\Column(length: 50)]
    private ?string $result = 'follow_up';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $des = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nextAction = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $nextDate = null;

    public function getId(): ?int { return $this->id; }

    public function getBid(): ?Business { return $this->bid; }
    public function setBid(?Business $bid): static { $this->bid = $bid; return $this; }

    public function getCenter(): ?SalesCenter { return $this->center; }
    public function setCenter(?SalesCenter $center): static { $this->center = $center; return $this; }

    public function getDeal(): ?SalesDeal { return $this->deal; }
    public function setDeal(?SalesDeal $deal): static { $this->deal = $deal; return $this; }

    public function getWeekPlan(): ?WeekPlan { return $this->weekPlan; }
    public function setWeekPlan(?WeekPlan $weekPlan): static { $this->weekPlan = $weekPlan; return $this; }

    public function getSubmitter(): ?User { return $this->submitter; }
    public function setSubmitter(?User $submitter): static { $this->submitter = $submitter; return $this; }

    public function getDate(): ?string { return $this->date; }
    public function setDate(string $date): static { $this->date = $date; return $this; }

    public function getResult(): ?string { return $this->result; }
    public function setResult(string $result): static { $this->result = $result; return $this; }

    public function getDes(): ?string { return $this->des; }
    public function setDes(?string $des): static { $this->des = $des; return $this; }

    public function getNextAction(): ?string { return $this->nextAction; }
    public function setNextAction(?string $nextAction): static { $this->nextAction = $nextAction; return $this; }

    public function getNextDate(): ?string { return $this->nextDate; }
    public function setNextDate(?string $nextDate): static { $this->nextDate = $nextDate; return $this; }
}
