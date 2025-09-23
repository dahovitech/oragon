<?php

namespace App\Bundle\AnalyticsBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Bundle\AnalyticsBundle\Repository\EventRepository')]
#[ORM\Table(name: 'analytics_events')]
#[ORM\Index(name: 'idx_events_date', columns: ['created_at'])]
#[ORM\Index(name: 'idx_events_type', columns: ['event_type'])]
#[ORM\Index(name: 'idx_events_category', columns: ['category'])]
#[ORM\Index(name: 'idx_events_user', columns: ['user_id'])]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $eventType;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $category;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $action = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $value = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $sessionId = null;

    #[ORM\Column(type: Types::STRING, length: 45)]
    private string $ipAddress;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $properties = null;

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

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getValue(): ?int
    {
        return $this->value;
    }

    public function setValue(?int $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getProperties(): ?array
    {
        return $this->properties;
    }

    public function setProperties(?array $properties): self
    {
        $this->properties = $properties;
        return $this;
    }
}