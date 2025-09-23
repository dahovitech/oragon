<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: "App\Repository\SecurityEventRepository")]
#[ORM\Table(name: "security_events")]
#[ORM\Index(columns: ["event_type"], name: "idx_security_event_type")]
#[ORM\Index(columns: ["user_id"], name: "idx_security_user")]
#[ORM\Index(columns: ["ip_address"], name: "idx_security_ip")]
#[ORM\Index(columns: ["created_at"], name: "idx_security_created")]
#[ORM\Index(columns: ["severity"], name: "idx_security_severity")]
class SecurityEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 100)]
    private string $eventType;

    #[ORM\Column(type: "string", length: 50)]
    private string $severity = 'info';

    #[ORM\Column(type: "string", length: 255)]
    private string $description;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: true)]
    private ?User $user = null;

    #[ORM\Column(type: "string", length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: "string", length: 500, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $requestUri = null;

    #[ORM\Column(type: "string", length: 10, nullable: true)]
    private ?string $requestMethod = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $contextData = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: "boolean")]
    private bool $resolved = false;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $resolution = null;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $resolvedBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): self
    {
        $this->eventType = $eventType;
        return $this;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): self
    {
        $this->severity = $severity;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getRequestUri(): ?string
    {
        return $this->requestUri;
    }

    public function setRequestUri(?string $requestUri): self
    {
        $this->requestUri = $requestUri;
        return $this;
    }

    public function getRequestMethod(): ?string
    {
        return $this->requestMethod;
    }

    public function setRequestMethod(?string $requestMethod): self
    {
        $this->requestMethod = $requestMethod;
        return $this;
    }

    public function getContextData(): ?array
    {
        return $this->contextData;
    }

    public function setContextData(?array $contextData): self
    {
        $this->contextData = $contextData;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function setResolved(bool $resolved): self
    {
        $this->resolved = $resolved;
        
        if ($resolved && !$this->resolvedAt) {
            $this->resolvedAt = new \DateTimeImmutable();
        } elseif (!$resolved) {
            $this->resolvedAt = null;
        }
        
        return $this;
    }

    public function getResolution(): ?string
    {
        return $this->resolution;
    }

    public function setResolution(?string $resolution): self
    {
        $this->resolution = $resolution;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function getResolvedBy(): ?int
    {
        return $this->resolvedBy;
    }

    public function setResolvedBy(?int $resolvedBy): self
    {
        $this->resolvedBy = $resolvedBy;
        return $this;
    }

    public function resolve(string $resolution, ?int $resolvedBy = null): self
    {
        $this->resolved = true;
        $this->resolution = $resolution;
        $this->resolvedAt = new \DateTimeImmutable();
        $this->resolvedBy = $resolvedBy;
        
        return $this;
    }

    public function isCritical(): bool
    {
        return in_array($this->severity, ['critical', 'high']);
    }

    public function addContextData(string $key, $value): self
    {
        $context = $this->contextData ?? [];
        $context[$key] = $value;
        $this->contextData = $context;
        
        return $this;
    }

    public function addMetadata(string $key, $value): self
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
        
        return $this;
    }

    public static function getEventTypes(): array
    {
        return [
            'login_attempt' => 'Tentative de connexion',
            'login_success' => 'Connexion réussie',
            'login_failure' => 'Échec de connexion',
            'logout' => 'Déconnexion',
            'password_change' => 'Changement de mot de passe',
            'password_reset' => 'Réinitialisation de mot de passe',
            'two_factor_enabled' => '2FA activé',
            'two_factor_disabled' => '2FA désactivé',
            'two_factor_failure' => 'Échec 2FA',
            'account_locked' => 'Compte verrouillé',
            'account_unlocked' => 'Compte déverrouillé',
            'permission_denied' => 'Accès refusé',
            'rate_limit_exceeded' => 'Limite de taux dépassée',
            'suspicious_activity' => 'Activité suspecte',
            'data_export' => 'Export de données',
            'admin_access' => 'Accès administrateur',
            'security_violation' => 'Violation de sécurité',
            'csrf_token_mismatch' => 'Jeton CSRF invalide',
            'sql_injection_attempt' => 'Tentative d\'injection SQL',
            'xss_attempt' => 'Tentative XSS',
            'file_upload_blocked' => 'Upload de fichier bloqué',
            'malware_detected' => 'Malware détecté',
        ];
    }

    public static function getSeverityLevels(): array
    {
        return [
            'info' => 'Information',
            'low' => 'Faible',
            'medium' => 'Moyen',
            'high' => 'Élevé',
            'critical' => 'Critique',
        ];
    }

    public function getSeverityLevel(): string
    {
        $levels = self::getSeverityLevels();
        return $levels[$this->severity] ?? $this->severity;
    }

    public function getEventTypeName(): string
    {
        $types = self::getEventTypes();
        return $types[$this->eventType] ?? $this->eventType;
    }
}