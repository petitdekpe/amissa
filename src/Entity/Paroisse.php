<?php

namespace App\Entity;

use App\Enum\TypeParoisse;
use App\Repository\ParoisseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParoisseRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Paroisse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Diocese::class, inversedBy: 'paroisses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Diocese $diocese = null;

    #[ORM\Column(type: Types::STRING, enumType: TypeParoisse::class)]
    private TypeParoisse $type = TypeParoisse::PAROISSE;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $numeroMobileMoney = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 2])]
    private int $delaiMinimumJours = 2;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /** @var Collection<int, Messe> */
    #[ORM\OneToMany(targetEntity: Messe::class, mappedBy: 'paroisse', orphanRemoval: true)]
    private Collection $messes;

    /** @var Collection<int, User> */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'paroisse')]
    private Collection $users;

    public function __construct()
    {
        $this->messes = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDiocese(): ?Diocese
    {
        return $this->diocese;
    }

    public function setDiocese(?Diocese $diocese): static
    {
        $this->diocese = $diocese;
        return $this;
    }

    public function getType(): TypeParoisse
    {
        return $this->type;
    }

    public function setType(TypeParoisse $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTypeLabel(): string
    {
        return $this->type->label();
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getNumeroMobileMoney(): ?string
    {
        return $this->numeroMobileMoney;
    }

    public function setNumeroMobileMoney(?string $numeroMobileMoney): static
    {
        $this->numeroMobileMoney = $numeroMobileMoney;
        return $this;
    }

    public function getDelaiMinimumJours(): int
    {
        return $this->delaiMinimumJours;
    }

    public function setDelaiMinimumJours(int $delaiMinimumJours): static
    {
        $this->delaiMinimumJours = $delaiMinimumJours;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, Messe> */
    public function getMesses(): Collection
    {
        return $this->messes;
    }

    public function addMesse(Messe $messe): static
    {
        if (!$this->messes->contains($messe)) {
            $this->messes->add($messe);
            $messe->setParoisse($this);
        }
        return $this;
    }

    public function removeMesse(Messe $messe): static
    {
        if ($this->messes->removeElement($messe)) {
            if ($messe->getParoisse() === $this) {
                $messe->setParoisse(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, User> */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function getDateLimiteIntention(): \DateTimeInterface
    {
        return (new \DateTime())->modify("+{$this->delaiMinimumJours} days");
    }
}
