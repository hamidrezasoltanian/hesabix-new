<?php

namespace App\Entity;

use App\Repository\CustomerNoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomerNoteRepository::class)]
#[ORM\Table(name: 'customer_note')]
class CustomerNote
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
    private ?Person $person = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 20)]
    private string $type = 'note';

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $reminderDate = null;

    #[ORM\Column(nullable: true)]
    private ?bool $reminderSent = false;

    #[ORM\Column(length: 10)]
    private ?string $date = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mentionedMobile = null;

    public function getId(): ?int { return $this->id; }

    public function getBid(): ?Business { return $this->bid; }
    public function setBid(?Business $bid): static { $this->bid = $bid; return $this; }

    public function getPerson(): ?Person { return $this->person; }
    public function setPerson(?Person $person): static { $this->person = $person; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getContent(): ?string { return $this->content; }
    public function setContent(?string $content): static { $this->content = $content; return $this; }

    public function getReminderDate(): ?string { return $this->reminderDate; }
    public function setReminderDate(?string $reminderDate): static { $this->reminderDate = $reminderDate; return $this; }

    public function isReminderSent(): ?bool { return $this->reminderSent; }
    public function setReminderSent(?bool $reminderSent): static { $this->reminderSent = $reminderSent; return $this; }

    public function getDate(): ?string { return $this->date; }
    public function setDate(?string $date): static { $this->date = $date; return $this; }

    public function getMentionedMobile(): ?string { return $this->mentionedMobile; }
    public function setMentionedMobile(?string $mentionedMobile): static { $this->mentionedMobile = $mentionedMobile; return $this; }
}
