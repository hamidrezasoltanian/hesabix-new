<?php

namespace App\Entity;

use App\Repository\SalesCenterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SalesCenterRepository::class)]
class SalesCenter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Business $bid = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $province = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(nullable: true)]
    private ?int $potential = null;

    // طبقه‌بندی لید: customer/lead/opportunity/clue/none/no_usage
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $lead = null;

    // وضعیت CRM مرکز: no_contact/initial_contact/meeting_done/proposal_sent/contract_closed/inactive
    #[ORM\Column(length: 30, nullable: true, options: ['default' => 'no_contact'])]
    private ?string $crmStatus = 'no_contact';

    // تاریخ پیگیری بعدی (شمسی YYYY/MM/DD)
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $followupDate = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $owner = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $tel = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column]
    private ?bool $active = true;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Person $person = null;

    #[ORM\ManyToMany(targetEntity: SalesCenterTag::class)]
    #[ORM\JoinTable(name: 'sales_center_tag_assignment')]
    private Collection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getBid(): ?Business { return $this->bid; }
    public function setBid(?Business $bid): static { $this->bid = $bid; return $this; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getProvince(): ?string { return $this->province; }
    public function setProvince(?string $province): static { $this->province = $province; return $this; }

    public function getCity(): ?string { return $this->city; }
    public function setCity(?string $city): static { $this->city = $city; return $this; }

    public function getType(): ?string { return $this->type; }
    public function setType(?string $type): static { $this->type = $type; return $this; }

    public function getPotential(): ?int { return $this->potential; }
    public function setPotential(?int $potential): static { $this->potential = $potential; return $this; }

    public function getLead(): ?string { return $this->lead; }
    public function setLead(?string $lead): static { $this->lead = $lead; return $this; }

    public function getCrmStatus(): ?string { return $this->crmStatus; }
    public function setCrmStatus(?string $crmStatus): static { $this->crmStatus = $crmStatus; return $this; }

    public function getFollowupDate(): ?string { return $this->followupDate; }
    public function setFollowupDate(?string $followupDate): static { $this->followupDate = $followupDate; return $this; }

    public function getOwner(): ?User { return $this->owner; }
    public function setOwner(?User $owner): static { $this->owner = $owner; return $this; }

    public function getTel(): ?string { return $this->tel; }
    public function setTel(?string $tel): static { $this->tel = $tel; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): static { $this->address = $address; return $this; }

    public function isActive(): ?bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }

    public function getPerson(): ?Person { return $this->person; }
    public function setPerson(?Person $person): static { $this->person = $person; return $this; }

    public function getTags(): Collection { return $this->tags; }
    public function addTag(SalesCenterTag $tag): static
    {
        if (!$this->tags->contains($tag)) $this->tags->add($tag);
        return $this;
    }
    public function removeTag(SalesCenterTag $tag): static
    {
        $this->tags->removeElement($tag);
        return $this;
    }
}
