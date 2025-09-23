<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: "App\Repository\TwoFactorAuthRepository")]
#[ORM\Table(name: "two_factor_auth")]
#[ORM\Index(columns: ["user_id"], name: "idx_2fa_user")]
class TwoFactorAuth
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private User $user;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $secret = null;

    #[ORM\Column(type: "boolean")]
    private bool $enabled = false;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $backupCodes = null;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $enabledAt = null;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: "integer")]
    private int $usageCount = 0;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $deviceName = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): self
    {
        $this->secret = $secret;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        $this->updatedAt = new \DateTimeImmutable();
        
        if ($enabled && !$this->enabledAt) {
            $this->enabledAt = new \DateTimeImmutable();
        } elseif (!$enabled) {
            $this->enabledAt = null;
        }
        
        return $this;
    }

    public function getBackupCodes(): ?array
    {
        return $this->backupCodes;
    }

    public function setBackupCodes(?array $backupCodes): self
    {
        $this->backupCodes = $backupCodes;
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

    public function getEnabledAt(): ?\DateTimeImmutable
    {
        return $this->enabledAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): self
    {
        $this->lastUsedAt = $lastUsedAt;
        return $this;
    }

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function setUsageCount(int $usageCount): self
    {
        $this->usageCount = $usageCount;
        return $this;
    }

    public function incrementUsageCount(): self
    {
        $this->usageCount++;
        $this->lastUsedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getDeviceName(): ?string
    {
        return $this->deviceName;
    }

    public function setDeviceName(?string $deviceName): self
    {
        $this->deviceName = $deviceName;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Generate new backup codes
     */
    public function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4))); // 8 characters
        }
        
        $this->backupCodes = $codes;
        $this->updatedAt = new \DateTimeImmutable();
        
        return $codes;
    }

    /**
     * Use a backup code
     */
    public function useBackupCode(string $code): bool
    {
        if (!$this->backupCodes) {
            return false;
        }

        $key = array_search(strtoupper($code), $this->backupCodes);
        if ($key !== false) {
            unset($this->backupCodes[$key]);
            $this->backupCodes = array_values($this->backupCodes); // Reindex array
            $this->incrementUsageCount();
            return true;
        }

        return false;
    }

    /**
     * Check if backup codes are available
     */
    public function hasBackupCodes(): bool
    {
        return !empty($this->backupCodes);
    }

    /**
     * Get remaining backup codes count
     */
    public function getRemainingBackupCodesCount(): int
    {
        return count($this->backupCodes ?? []);
    }

    /**
     * Check if 2FA is fully configured
     */
    public function isFullyConfigured(): bool
    {
        return $this->enabled && !empty($this->secret) && $this->hasBackupCodes();
    }

    /**
     * Reset 2FA configuration
     */
    public function reset(): self
    {
        $this->enabled = false;
        $this->secret = null;
        $this->backupCodes = null;
        $this->enabledAt = null;
        $this->lastUsedAt = null;
        $this->usageCount = 0;
        $this->deviceName = null;
        $this->updatedAt = new \DateTimeImmutable();
        
        return $this;
    }

    /**
     * Get QR code URL for authenticator apps
     */
    public function getQrCodeUrl(string $issuer = 'Oragon Platform'): ?string
    {
        if (!$this->secret) {
            return null;
        }

        $email = $this->user->getEmail();
        $label = urlencode($issuer . ':' . $email);
        
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s',
            $label,
            $this->secret,
            urlencode($issuer)
        );
    }

    /**
     * Get manual entry key for authenticator apps
     */
    public function getManualEntryKey(): ?string
    {
        if (!$this->secret) {
            return null;
        }

        // Format secret for manual entry (groups of 4 characters)
        return implode(' ', str_split($this->secret, 4));
    }
}