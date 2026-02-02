<?php

namespace App\Entity;

use App\Enum\RecurrenceMesse;
use App\Enum\StatutMesse;
use App\Enum\TypeMesse;
use App\Repository\MesseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MesseRepository::class)]
class Messe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Paroisse::class, inversedBy: 'messes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Paroisse $paroisse = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::STRING, enumType: TypeMesse::class)]
    private TypeMesse $type;

    #[ORM\Column(type: Types::STRING, enumType: RecurrenceMesse::class, nullable: true)]
    private ?RecurrenceMesse $recurrence = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $heure = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $jourSemaine = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $montantSuggere = null;

    #[ORM\Column(type: Types::STRING, enumType: StatutMesse::class)]
    private StatutMesse $statut = StatutMesse::ACTIVE;

    /** @var Collection<int, OccurrenceMesse> */
    #[ORM\OneToMany(targetEntity: OccurrenceMesse::class, mappedBy: 'messe', orphanRemoval: true)]
    private Collection $occurrences;

    public function __construct()
    {
        $this->occurrences = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParoisse(): ?Paroisse
    {
        return $this->paroisse;
    }

    public function setParoisse(?Paroisse $paroisse): static
    {
        $this->paroisse = $paroisse;
        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getType(): TypeMesse
    {
        return $this->type;
    }

    public function setType(TypeMesse $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function isRecurrente(): bool
    {
        return $this->type === TypeMesse::RECURRENTE;
    }

    public function getRecurrence(): ?RecurrenceMesse
    {
        return $this->recurrence;
    }

    public function setRecurrence(?RecurrenceMesse $recurrence): static
    {
        $this->recurrence = $recurrence;
        return $this;
    }

    public function getHeure(): ?\DateTimeInterface
    {
        return $this->heure;
    }

    public function setHeure(\DateTimeInterface $heure): static
    {
        $this->heure = $heure;
        return $this;
    }

    public function getJourSemaine(): ?int
    {
        return $this->jourSemaine;
    }

    public function setJourSemaine(?int $jourSemaine): static
    {
        $this->jourSemaine = $jourSemaine;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getMontantSuggere(): ?string
    {
        return $this->montantSuggere;
    }

    public function setMontantSuggere(string $montantSuggere): static
    {
        $this->montantSuggere = $montantSuggere;
        return $this;
    }

    public function getStatut(): StatutMesse
    {
        return $this->statut;
    }

    public function setStatut(StatutMesse $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->statut === StatutMesse::ACTIVE;
    }

    /** @return Collection<int, OccurrenceMesse> */
    public function getOccurrences(): Collection
    {
        return $this->occurrences;
    }

    public function addOccurrence(OccurrenceMesse $occurrence): static
    {
        if (!$this->occurrences->contains($occurrence)) {
            $this->occurrences->add($occurrence);
            $occurrence->setMesse($this);
        }
        return $this;
    }

    public function removeOccurrence(OccurrenceMesse $occurrence): static
    {
        if ($this->occurrences->removeElement($occurrence)) {
            if ($occurrence->getMesse() === $this) {
                $occurrence->setMesse(null);
            }
        }
        return $this;
    }

    public function getJourSemaineLabel(): ?string
    {
        if ($this->jourSemaine === null) {
            return null;
        }

        $jours = [
            0 => 'Dimanche',
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
        ];

        return $jours[$this->jourSemaine] ?? null;
    }
}
