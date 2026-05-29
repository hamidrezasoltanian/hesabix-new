<?php

namespace App\Entity;

use App\Repository\WeekPlanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WeekPlanRepository::class)]
class WeekPlan
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
    #[ORM\JoinColumn(nullable: false)]
    private ?User $submitter = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $owner = null;

    #[ORM\Column(length: 20)]
    private ?string $weekStr = null;

    #[ORM\Column(length: 20)]
    private ?string $scheduledDate = null;

    #[ORM\Column(length: 20)]
    private ?string $actionType = 'visit';

    #[ORM\Column]
    private ?bool $done = false;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $doneDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $des = null;

    public function getId(): ?int { return $this->id; }

    public function getBid(): ?Business { return $this->bid; }
    public function setBid(?Business $bid): static { $this->bid = $bid; return $this; }

    public function getYear(): ?Year { return $this->year; }
    public function setYear(?Year $year): static { $this->year = $year; return $this; }

    public function getCenter(): ?SalesCenter { return $this->center; }
    public function setCenter(?SalesCenter $center): static { $this->center = $center; return $this; }

    public function getSubmitter(): ?User { return $this->submitter; }
    public function setSubmitter(?User $submitter): static { $this->submitter = $submitter; return $this; }

    public function getOwner(): ?User { return $this->owner; }
    public function setOwner(?User $owner): static { $this->owner = $owner; return $this; }

    public function getWeekStr(): ?string { return $this->weekStr; }
    public function setWeekStr(string $weekStr): static { $this->weekStr = $weekStr; return $this; }

    public function getScheduledDate(): ?string { return $this->scheduledDate; }
    public function setScheduledDate(string $scheduledDate): static { $this->scheduledDate = $scheduledDate; return $this; }

    public function getActionType(): ?string { return $this->actionType; }
    public function setActionType(string $actionType): static { $this->actionType = $actionType; return $this; }

    public function isDone(): ?bool { return $this->done; }
    public function setDone(bool $done): static { $this->done = $done; return $this; }

    public function getDoneDate(): ?string { return $this->doneDate; }
    public function setDoneDate(?string $doneDate): static { $this->doneDate = $doneDate; return $this; }

    public function getDes(): ?string { return $this->des; }
    public function setDes(?string $des): static { $this->des = $des; return $this; }
}
