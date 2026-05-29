<?php
namespace App\Entity;
use App\Repository\KpiTargetRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KpiTargetRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_kpi_target', columns: ['bid_id','user_id','month'])]
class KpiTarget
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)]
    private ?Business $bid = null;

    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    // YYYY/MM  e.g. 1403/06
    #[ORM\Column(length: 10)]
    private ?string $month = null;

    #[ORM\Column(options: ['default' => 10])]
    private int $callDailyTarget = 10;

    #[ORM\Column(options: ['default' => 5])]
    private int $visitWeeklyTarget = 5;

    #[ORM\Column(options: ['default' => 2])]
    private int $saleMonthlyTarget = 2;

    #[ORM\Column(length: 30, options: ['default' => '0'])]
    private string $saleAmountTarget = '0';

    #[ORM\Column(options: ['default' => 80])]
    private int $retentionPctTarget = 80;

    #[ORM\Column(options: ['default' => 2])]
    private int $missionMonthlyTarget = 2;

    #[ORM\Column(options: ['default' => 30])]
    private int $cashPctTarget = 30;

    public function getId(): ?int { return $this->id; }
    public function getBid(): ?Business { return $this->bid; }
    public function setBid(?Business $bid): static { $this->bid = $bid; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getMonth(): ?string { return $this->month; }
    public function setMonth(string $month): static { $this->month = $month; return $this; }
    public function getCallDailyTarget(): int { return $this->callDailyTarget; }
    public function setCallDailyTarget(int $v): static { $this->callDailyTarget = $v; return $this; }
    public function getVisitWeeklyTarget(): int { return $this->visitWeeklyTarget; }
    public function setVisitWeeklyTarget(int $v): static { $this->visitWeeklyTarget = $v; return $this; }
    public function getSaleMonthlyTarget(): int { return $this->saleMonthlyTarget; }
    public function setSaleMonthlyTarget(int $v): static { $this->saleMonthlyTarget = $v; return $this; }
    public function getSaleAmountTarget(): string { return $this->saleAmountTarget; }
    public function setSaleAmountTarget(string $v): static { $this->saleAmountTarget = $v; return $this; }
    public function getRetentionPctTarget(): int { return $this->retentionPctTarget; }
    public function setRetentionPctTarget(int $v): static { $this->retentionPctTarget = $v; return $this; }
    public function getMissionMonthlyTarget(): int { return $this->missionMonthlyTarget; }
    public function setMissionMonthlyTarget(int $v): static { $this->missionMonthlyTarget = $v; return $this; }
    public function getCashPctTarget(): int { return $this->cashPctTarget; }
    public function setCashPctTarget(int $v): static { $this->cashPctTarget = $v; return $this; }
}
