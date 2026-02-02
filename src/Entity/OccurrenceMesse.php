<?php

namespace App\Entity;

use App\Enum\StatutOccurrence;
use App\Repository\OccurrenceMesseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OccurrenceMesseRepository::class)]
#[ORM\Index(columns: ['date_heure'], name: 'idx_occurrence_date_heure')]
class OccurrenceMesse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Messe::class, inversedBy: 'occurrences')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Messe $messe = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateHeure = null;

    #[ORM\Column(type: Types::STRING, enumType: StatutOccurrence::class)]
    private StatutOccurrence $statut = StatutOccurrence::CONFIRMEE;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $nombreIntentions = 0;

    /** @var Collection<int, IntentionMesse> */
    #[ORM\OneToMany(targetEntity: IntentionMesse::class, mappedBy: 'occurrence', orphanRemoval: true)]
    private Collection $intentions;

    public function __construct()
    {
        $this->intentions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMesse(): ?Messe
    {
        return $this->messe;
    }

    public function setMesse(?Messe $messe): static
    {
        $this->messe = $messe;
        return $this;
    }

    public function getDateHeure(): ?\DateTimeInterface
    {
        return $this->dateHeure;
    }

    public function setDateHeure(\DateTimeInterface $dateHeure): static
    {
        $this->dateHeure = $dateHeure;
        return $this;
    }

    public function getStatut(): StatutOccurrence
    {
        return $this->statut;
    }

    public function setStatut(StatutOccurrence $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function isConfirmee(): bool
    {
        return $this->statut === StatutOccurrence::CONFIRMEE;
    }

    public function isAnnulee(): bool
    {
        return $this->statut === StatutOccurrence::ANNULEE;
    }

    public function getNombreIntentions(): int
    {
        return $this->nombreIntentions;
    }

    public function setNombreIntentions(int $nombreIntentions): static
    {
        $this->nombreIntentions = $nombreIntentions;
        return $this;
    }

    public function incrementNombreIntentions(): static
    {
        $this->nombreIntentions++;
        return $this;
    }

    /** @return Collection<int, IntentionMesse> */
    public function getIntentions(): Collection
    {
        return $this->intentions;
    }

    public function addIntention(IntentionMesse $intention): static
    {
        if (!$this->intentions->contains($intention)) {
            $this->intentions->add($intention);
            $intention->setOccurrence($this);
            $this->incrementNombreIntentions();
        }
        return $this;
    }

    public function removeIntention(IntentionMesse $intention): static
    {
        if ($this->intentions->removeElement($intention)) {
            if ($intention->getOccurrence() === $this) {
                $intention->setOccurrence(null);
            }
            $this->nombreIntentions = max(0, $this->nombreIntentions - 1);
        }
        return $this;
    }

    public function isPast(): bool
    {
        return $this->dateHeure < new \DateTime();
    }

    public function canReceiveIntentions(): bool
    {
        if (!$this->isConfirmee()) {
            return false;
        }

        $paroisse = $this->messe?->getParoisse();
        if (!$paroisse) {
            return false;
        }

        $dateLimite = $paroisse->getDateLimiteIntention();
        return $this->dateHeure > $dateLimite;
    }
}
