<?php

namespace App\Bundle\NotificationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: "App\Bundle\NotificationBundle\Repository\NotificationPreferenceRepository")]
#[ORM\Table(name: "notification_preferences")]
#[ORM\UniqueConstraint(columns: ["user_id", "type"])]
#[ORM\Index(columns: ["user_id"], name: "idx_preferences_user")]
class NotificationPreference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "integer")]
    private int $userId;

    #[ORM\Column(type: "string", length: 100)]
    private string $type;

    #[ORM\Column(type: "json")]
    private array $channels = [];

    #[ORM\Column(type: "boolean")]
    private bool $enabled = true;

    #[ORM\Column(type: "string", length: 50)]
    private string $frequency = 'immediate';

    #[ORM\Column(type: "time", nullable: true)]
    private ?\DateTimeInterface $quietHoursStart = null;

    #[ORM\Column(type: "time", nullable: true)]
    private ?\DateTimeInterface $quietHoursEnd = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $settings = null;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
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

    public function getChannels(): array
    {
        return $this->channels;
    }

    public function setChannels(array $channels): self
    {
        $this->channels = $channels;
        return $this;
    }

    public function addChannel(string $channel): self
    {
        if (!in_array($channel, $this->channels)) {
            $this->channels[] = $channel;
        }
        return $this;
    }

    public function removeChannel(string $channel): self
    {
        $this->channels = array_filter($this->channels, fn($c) => $c !== $channel);
        return $this;
    }

    public function hasChannel(string $channel): bool
    {
        return in_array($channel, $this->channels);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): self
    {
        $this->frequency = $frequency;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getQuietHoursStart(): ?\DateTimeInterface
    {
        return $this->quietHoursStart;
    }

    public function setQuietHoursStart(?\DateTimeInterface $quietHoursStart): self
    {
        $this->quietHoursStart = $quietHoursStart;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getQuietHoursEnd(): ?\DateTimeInterface
    {
        return $this->quietHoursEnd;
    }

    public function setQuietHoursEnd(?\DateTimeInterface $quietHoursEnd): self
    {
        $this->quietHoursEnd = $quietHoursEnd;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getSettings(): ?array
    {
        return $this->settings;
    }

    public function setSettings(?array $settings): self
    {
        $this->settings = $settings;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isInQuietHours(): bool
    {
        if (!$this->quietHoursStart || !$this->quietHoursEnd) {
            return false;
        }

        $now = new \DateTime();
        $currentTime = $now->format('H:i:s');
        $startTime = $this->quietHoursStart->format('H:i:s');
        $endTime = $this->quietHoursEnd->format('H:i:s');

        if ($startTime <= $endTime) {
            // Same day range (e.g., 09:00 to 17:00)
            return $currentTime >= $startTime && $currentTime <= $endTime;
        } else {
            // Overnight range (e.g., 22:00 to 06:00)
            return $currentTime >= $startTime || $currentTime <= $endTime;
        }
    }

    public function shouldReceiveNotification(string $channel): bool
    {
        return $this->enabled && 
               $this->hasChannel($channel) && 
               ($this->frequency === 'immediate' || !$this->isInQuietHours());
    }
}