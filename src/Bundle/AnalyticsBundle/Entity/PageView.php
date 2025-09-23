<?php

namespace App\Bundle\AnalyticsBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Bundle\AnalyticsBundle\Repository\PageViewRepository')]
#[ORM\Table(name: 'analytics_page_views')]
#[ORM\Index(name: 'idx_page_views_date', columns: ['created_at'])]
#[ORM\Index(name: 'idx_page_views_url', columns: ['url'])]
#[ORM\Index(name: 'idx_page_views_user', columns: ['user_id'])]
class PageView
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $url;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $referrer = null;

    #[ORM\Column(type: Types::STRING, length: 45)]
    private string $ipAddress;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $sessionId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $viewDuration = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function setReferrer(?string $referrer): self
    {
        $this->referrer = $referrer;
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

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getViewDuration(): ?int
    {
        return $this->viewDuration;
    }

    public function setViewDuration(?int $viewDuration): self
    {
        $this->viewDuration = $viewDuration;
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
}