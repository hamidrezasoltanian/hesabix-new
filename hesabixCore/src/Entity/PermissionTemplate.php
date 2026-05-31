<?php

namespace App\Entity;

use App\Repository\PermissionTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PermissionTemplateRepository::class)]
#[ORM\Table(name: 'permission_template')]
class PermissionTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Business $bid = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: Types::JSON)]
    private array $permissions = [];

    public function getId(): ?int { return $this->id; }

    public function getBid(): ?Business { return $this->bid; }
    public function setBid(?Business $bid): static { $this->bid = $bid; return $this; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): static { $this->name = $name; return $this; }

    public function getPermissions(): array { return $this->permissions; }
    public function setPermissions(array $permissions): static { $this->permissions = $permissions; return $this; }
}
