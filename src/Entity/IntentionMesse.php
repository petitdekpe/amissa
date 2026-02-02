<?php

namespace App\Entity;

use App\Enum\StatutPaiement;
use App\Enum\StatutPayout;
use App\Repository\IntentionMesseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IntentionMesseRepository::class)]
#[ORM\HasLifecycleCallbacks]
class IntentionMesse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: OccurrenceMesse::class, inversedBy: 'intentions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?OccurrenceMesse $occurrence = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $fidele = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $numeroReference = null;

    #[ORM\Column(length: 255)]
    private ?string $nomDemandeur = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $beneficiaire = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $typeIntention = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $texteIntention = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $montantPaye = null;

    #[ORM\Column(type: Types::STRING, enumType: StatutPaiement::class)]
    private StatutPaiement $statutPaiement = StatutPaiement::EN_ATTENTE;

    #[ORM\Column(length: 100, unique: true, nullable: true)]
    private ?string $transactionFedapay = null;

    #[ORM\Column(type: Types::STRING, enumType: StatutPayout::class)]
    private StatutPayout $statutPayout = StatutPayout::EN_ATTENTE;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $payoutReference = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();

        if ($this->numeroReference === null) {
            $this->numeroReference = $this->generateNumeroReference();
        }
    }

    private function generateNumeroReference(): string
    {
        $year = date('Y');
        $random = strtoupper(bin2hex(random_bytes(4)));
        return "INT-{$year}-{$random}";
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

    public function getOccurrence(): ?OccurrenceMesse
    {
        return $this->occurrence;
    }

    public function setOccurrence(?OccurrenceMesse $occurrence): static
    {
        $this->occurrence = $occurrence;
        return $this;
    }

    public function getFidele(): ?User
    {
        return $this->fidele;
    }

    public function setFidele(?User $fidele): static
    {
        $this->fidele = $fidele;
        return $this;
    }

    public function getNumeroReference(): ?string
    {
        return $this->numeroReference;
    }

    public function setNumeroReference(string $numeroReference): static
    {
        $this->numeroReference = $numeroReference;
        return $this;
    }

    public function getNomDemandeur(): ?string
    {
        return $this->nomDemandeur;
    }

    public function setNomDemandeur(string $nomDemandeur): static
    {
        $this->nomDemandeur = $nomDemandeur;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getContact(): string
    {
        return $this->telephone ?? $this->email ?? '';
    }

    public function getBeneficiaire(): ?string
    {
        return $this->beneficiaire;
    }

    public function setBeneficiaire(?string $beneficiaire): static
    {
        $this->beneficiaire = $beneficiaire;
        return $this;
    }

    public function getBeneficiaireDisplay(): string
    {
        return $this->beneficiaire ?? 'un(e) fidèle';
    }

    public function getTypeIntention(): ?string
    {
        return $this->typeIntention;
    }

    public function setTypeIntention(?string $typeIntention): static
    {
        $this->typeIntention = $typeIntention;
        return $this;
    }

    public function getTexteIntention(): ?string
    {
        return $this->texteIntention;
    }

    public function setTexteIntention(?string $texteIntention): static
    {
        $this->texteIntention = $texteIntention;
        return $this;
    }

    public function getMontantPaye(): ?string
    {
        return $this->montantPaye;
    }

    public function setMontantPaye(string $montantPaye): static
    {
        $this->montantPaye = $montantPaye;
        return $this;
    }

    public function getStatutPaiement(): StatutPaiement
    {
        return $this->statutPaiement;
    }

    public function setStatutPaiement(StatutPaiement $statutPaiement): static
    {
        $this->statutPaiement = $statutPaiement;
        return $this;
    }

    public function isPaye(): bool
    {
        return $this->statutPaiement === StatutPaiement::PAYE;
    }

    public function getTransactionFedapay(): ?string
    {
        return $this->transactionFedapay;
    }

    public function setTransactionFedapay(?string $transactionFedapay): static
    {
        $this->transactionFedapay = $transactionFedapay;
        return $this;
    }

    public function getStatutPayout(): StatutPayout
    {
        return $this->statutPayout;
    }

    public function setStatutPayout(StatutPayout $statutPayout): static
    {
        $this->statutPayout = $statutPayout;
        return $this;
    }

    public function isPayoutTransfere(): bool
    {
        return $this->statutPayout === StatutPayout::TRANSFERE;
    }

    public function getPayoutReference(): ?string
    {
        return $this->payoutReference;
    }

    public function setPayoutReference(?string $payoutReference): static
    {
        $this->payoutReference = $payoutReference;
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

    public function getTypeIntentionLabel(): string
    {
        if ($this->typeIntention === null) {
            return 'Intention rédigée';
        }

        $labels = [
            'repos_ame' => 'Repos de l\'âme',
            'action_grace' => 'Action de grâce',
            'guerison' => 'Demande de guérison',
            'particuliere' => 'Intention particulière',
            'anniversaire' => 'Anniversaire',
            'mariage' => 'Mariage',
            'defunt' => 'Pour un défunt',
        ];

        return $labels[$this->typeIntention] ?? $this->typeIntention;
    }

    public function getParoisse(): ?Paroisse
    {
        return $this->occurrence?->getMesse()?->getParoisse();
    }

    public function getDiocese(): ?Diocese
    {
        return $this->getParoisse()?->getDiocese();
    }
}
