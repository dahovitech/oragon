<?php

namespace App\Bundle\NotificationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: "App\Bundle\NotificationBundle\Repository\NotificationRepository")]
#[ORM\Table(name: "notifications")]
#[ORM\Index(columns: ["user_id"], name: "idx_notification_user")]
#[ORM\Index(columns: ["type"], name: "idx_notification_type")]
#[ORM\Index(columns: ["status"], name: "idx_notification_status")]
#[ORM\Index(columns: ["created_at"], name: "idx_notification_created")]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 100)]
    private string $type;

    #[ORM\Column(type: "string", length: 255)]
    private string $title;

    #[ORM\Column(type: "text")]
    private string $message;

    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $userEmail = null;

    #[ORM\Column(type: "string", length: 50)]
    private string $status = 'pending';

    #[ORM\Column(type: "string", length: 50)]
    private string $priority = 'normal';

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: "string", length: 500, nullable: true)]
    private ?string $actionUrl = null;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $actionText = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $data = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $channels = null;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(type: "integer")]
    private int $attempts = 0;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $failureReason = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
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

    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function setUserEmail(?string $userEmail): self
    {
        $this->userEmail = $userEmail;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getActionUrl(): ?string
    {
        return $this->actionUrl;
    }

    public function setActionUrl(?string $actionUrl): self
    {
        $this->actionUrl = $actionUrl;
        return $this;
    }

    public function getActionText(): ?string
    {
        return $this->actionText;
    }

    public function setActionText(?string $actionText): self
    {
        $this->actionText = $actionText;
        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getChannels(): ?array
    {
        return $this->channels;
    }

    public function setChannels(?array $channels): self
    {
        $this->channels = $channels;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): self
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;
        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function setAttempts(int $attempts): self
    {
        $this->attempts = $attempts;
        return $this;
    }

    public function incrementAttempts(): self
    {
        $this->attempts++;
        return $this;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): self
    {
        $this->failureReason = $failureReason;
        return $this;
    }

    public function markAsSent(): self
    {
        $this->status = 'sent';
        $this->sentAt = new \DateTimeImmutable();
        return $this;
    }

    public function markAsRead(): self
    {
        if (!$this->readAt) {
            $this->readAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function markAsFailed(string $reason): self
    {
        $this->status = 'failed';
        $this->failureReason = $reason;
        $this->incrementAttempts();
        return $this;
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isScheduled(): bool
    {
        return $this->scheduledAt !== null && $this->scheduledAt > new \DateTimeImmutable();
    }

    public function canBeResent(): bool
    {
        return $this->isFailed() && $this->attempts < 3;
    }
}