<?php

namespace App\Entity;

use App\Repository\PrintTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PrintTemplateRepository::class)]
class PrintTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'printTemplates')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Business $bid = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $fastSellInvoice = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cashdeskTicket = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $invoiceTemplate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $preinvoiceTemplate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $storeroomTemplate = null;

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

    public function getFastSellInvoice(): ?string
    {
        return $this->fastSellInvoice;
    }

    public function setFastSellInvoice(?string $fastSellInvoice): static
    {
        $this->fastSellInvoice = $fastSellInvoice;

        return $this;
    }

    public function getCashdeskTicket(): ?string
    {
        return $this->cashdeskTicket;
    }

    public function setCashdeskTicket(?string $cashdeskTicket): static
    {
        $this->cashdeskTicket = $cashdeskTicket;

        return $this;
    }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): static { $this->name = $name; return $this; }

    public function getInvoiceTemplate(): ?string { return $this->invoiceTemplate; }
    public function setInvoiceTemplate(?string $invoiceTemplate): static { $this->invoiceTemplate = $invoiceTemplate; return $this; }

    public function getPreinvoiceTemplate(): ?string { return $this->preinvoiceTemplate; }
    public function setPreinvoiceTemplate(?string $preinvoiceTemplate): static { $this->preinvoiceTemplate = $preinvoiceTemplate; return $this; }

    public function getStoreroomTemplate(): ?string { return $this->storeroomTemplate; }
    public function setStoreroomTemplate(?string $storeroomTemplate): static { $this->storeroomTemplate = $storeroomTemplate; return $this; }
}
