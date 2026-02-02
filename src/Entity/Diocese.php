<?php

namespace App\Entity;

use App\Enum\StatutDiocese;
use App\Repository\DioceseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DioceseRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Diocese
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::STRING, enumType: StatutDiocese::class)]
    private StatutDiocese $statut = StatutDiocese::INACTIF;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fedapaySecretKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fedapayPublicKey = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /** @var Collection<int, Paroisse> */
    #[ORM\OneToMany(targetEntity: Paroisse::class, mappedBy: 'diocese', orphanRemoval: true)]
    private Collection $paroisses;

    /** @var Collection<int, User> */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'diocese')]
    private Collection $users;

    public function __construct()
    {
        $this->paroisses = new ArrayCollection();
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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getStatut(): StatutDiocese
    {
        return $this->statut;
    }

    public function setStatut(StatutDiocese $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function isActif(): bool
    {
        return $this->statut === StatutDiocese::ACTIF;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getFedapaySecretKey(): ?string
    {
        return $this->fedapaySecretKey;
    }

    public function setFedapaySecretKey(?string $fedapaySecretKey): static
    {
        $this->fedapaySecretKey = $fedapaySecretKey;
        return $this;
    }

    public function getFedapayPublicKey(): ?string
    {
        return $this->fedapayPublicKey;
    }

    public function setFedapayPublicKey(?string $fedapayPublicKey): static
    {
        $this->fedapayPublicKey = $fedapayPublicKey;
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

    /** @return Collection<int, Paroisse> */
    public function getParoisses(): Collection
    {
        return $this->paroisses;
    }

    public function addParoisse(Paroisse $paroisse): static
    {
        if (!$this->paroisses->contains($paroisse)) {
            $this->paroisses->add($paroisse);
            $paroisse->setDiocese($this);
        }
        return $this;
    }

    public function removeParoisse(Paroisse $paroisse): static
    {
        if ($this->paroisses->removeElement($paroisse)) {
            if ($paroisse->getDiocese() === $this) {
                $paroisse->setDiocese(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, User> */
    public function getUsers(): Collection
    {
        return $this->users;
    }
}
