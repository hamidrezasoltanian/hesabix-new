<?php
namespace App\Entity;
use App\Repository\CrmChecklistLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CrmChecklistLogRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_checklist_log', columns: ['bid_id','user_id','date','item_id'])]
class CrmChecklistLog
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)]
    private ?Business $bid = null;

    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 20)]
    private ?string $date = null;

    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CrmChecklistItem $item = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $done = false;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $note = null;

    public function getId(): ?int { return $this->id; }
    public function getBid(): ?Business { return $this->bid; }
    public function setBid(?Business $bid): static { $this->bid = $bid; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getDate(): ?string { return $this->date; }
    public function setDate(string $date): static { $this->date = $date; return $this; }
    public function getItem(): ?CrmChecklistItem { return $this->item; }
    public function setItem(?CrmChecklistItem $item): static { $this->item = $item; return $this; }
    public function isDone(): bool { return $this->done; }
    public function setDone(bool $done): static { $this->done = $done; return $this; }
    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $note): static { $this->note = $note; return $this; }
}
