<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Index(columns: ['category'], name: 'idx_activity_category')]
#[ORM\Index(columns: ['created_at'], name: 'idx_activity_created_at')]
#[ORM\Index(columns: ['user_id'], name: 'idx_activity_user')]
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'idx_activity_entity')]
class ActivityLog
{
    public const CATEGORY_AUTH = 'auth';
    public const CATEGORY_PAYMENT = 'payment';
    public const CATEGORY_INTENTION = 'intention';
    public const CATEGORY_ADMIN = 'admin';
    public const CATEGORY_WEBHOOK = 'webhook';
    public const CATEGORY_SYSTEM = 'system';

    public const LEVEL_DEBUG = 'debug';
    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';
    public const LEVEL_CRITICAL = 'critical';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $category = null;

    #[ORM\Column(length: 100)]
    private ?string $action = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column(length: 20)]
    private string $level = self::LEVEL_INFO;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(nullable: true)]
    private ?int $entityId = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $context = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $oldValues = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $newValues = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function setLevel(string $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(?string $entityType): static
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(?int $entityId): static
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function setContext(?array $context): static
    {
        $this->context = $context;
        return $this;
    }

    public function getOldValues(): ?array
    {
        return $this->oldValues;
    }

    public function setOldValues(?array $oldValues): static
    {
        $this->oldValues = $oldValues;
        return $this;
    }

    public function getNewValues(): ?array
    {
        return $this->newValues;
    }

    public function setNewValues(?array $newValues): static
    {
        $this->newValues = $newValues;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getLevelBadgeClass(): string
    {
        return match ($this->level) {
            self::LEVEL_DEBUG => 'bg-secondary',
            self::LEVEL_INFO => 'bg-info',
            self::LEVEL_WARNING => 'bg-warning',
            self::LEVEL_ERROR => 'bg-danger',
            self::LEVEL_CRITICAL => 'bg-dark',
            default => 'bg-secondary',
        };
    }

    public function getCategoryBadgeClass(): string
    {
        return match ($this->category) {
            self::CATEGORY_AUTH => 'bg-primary',
            self::CATEGORY_PAYMENT => 'bg-success',
            self::CATEGORY_INTENTION => 'bg-info',
            self::CATEGORY_ADMIN => 'bg-warning',
            self::CATEGORY_WEBHOOK => 'bg-secondary',
            self::CATEGORY_SYSTEM => 'bg-dark',
            default => 'bg-secondary',
        };
    }
}
