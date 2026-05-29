<?php
namespace App\Entity;
use App\Repository\CrmCalendarEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CrmCalendarEventRepository::class)]
class CrmCalendarEvent
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)]
    private ?Business $bid = null;

    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 20)]
    private ?string $date = null;        // شمسی YYYY/MM/DD

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $endDate = null;

    #[ORM\Column(length: 20, options: ['default' => '#1976D2'])]
    private ?string $color = '#1976D2';

    #[ORM\Column(options: ['default' => false])]
    private bool $allDay = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $des = null;

    public function getId(): ?int { return $this->id; }
    public function getBid(): ?Business { return $this->bid; }
    public function setBid(?Business $bid): static { $this->bid = $bid; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getDate(): ?string { return $this->date; }
    public function setDate(string $date): static { $this->date = $date; return $this; }
    public function getEndDate(): ?string { return $this->endDate; }
    public function setEndDate(?string $endDate): static { $this->endDate = $endDate; return $this; }
    public function getColor(): ?string { return $this->color; }
    public function setColor(string $color): static { $this->color = $color; return $this; }
    public function isAllDay(): bool { return $this->allDay; }
    public function setAllDay(bool $allDay): static { $this->allDay = $allDay; return $this; }
    public function getDes(): ?string { return $this->des; }
    public function setDes(?string $des): static { $this->des = $des; return $this; }
}
