<?php

namespace App\Service;

use App\Entity\TwoFactorAuth;
use App\Entity\User;
use App\Repository\TwoFactorAuthRepository;
use Doctrine\ORM\EntityManagerInterface;

class TwoFactorService
{
    private TwoFactorAuthRepository $twoFactorRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        TwoFactorAuthRepository $twoFactorRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->twoFactorRepository = $twoFactorRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * Initialize 2FA setup for user
     */
    public function initializeSetup(User $user, ?string $deviceName = null): TwoFactorAuth
    {
        $twoFactor = $this->twoFactorRepository->findOrCreateForUser($user);
        
        // Generate new secret
        $secret = $this->generateSecret();
        $twoFactor->setSecret($secret);
        
        if ($deviceName) {
            $twoFactor->setDeviceName($deviceName);
        }
        
        $this->twoFactorRepository->save($twoFactor, true);
        
        return $twoFactor;
    }

    /**
     * Complete 2FA setup after verification
     */
    public function completeSetup(User $user, string $code): bool
    {
        $twoFactor = $this->twoFactorRepository->findByUser($user);
        
        if (!$twoFactor || !$twoFactor->getSecret()) {
            return false;
        }
        
        // Verify the provided code
        if (!$this->verifyTOTP($twoFactor->getSecret(), $code)) {
            return false;
        }
        
        // Enable 2FA and generate backup codes
        $twoFactor->setEnabled(true);
        $twoFactor->generateBackupCodes();
        
        $this->twoFactorRepository->save($twoFactor, true);
        
        return true;
    }

    /**
     * Verify TOTP code
     */
    public function verifyCode(User $user, string $code): bool
    {
        $twoFactor = $this->twoFactorRepository->findByUser($user);
        
        if (!$twoFactor || !$twoFactor->isEnabled()) {
            return false;
        }
        
        // Try TOTP first
        if ($this->verifyTOTP($twoFactor->getSecret(), $code)) {
            $this->twoFactorRepository->updateLastUsed($twoFactor);
            return true;
        }
        
        // Try backup code
        if ($twoFactor->useBackupCode($code)) {
            $this->twoFactorRepository->save($twoFactor, true);
            return true;
        }
        
        return false;
    }

    /**
     * Generate backup codes
     */
    public function generateNewBackupCodes(User $user): ?array
    {
        $twoFactor = $this->twoFactorRepository->findByUser($user);
        
        if (!$twoFactor || !$twoFactor->isEnabled()) {
            return null;
        }
        
        $codes = $twoFactor->generateBackupCodes();
        $this->twoFactorRepository->save($twoFactor, true);
        
        return $codes;
    }

    /**
     * Disable 2FA for user
     */
    public function disable(User $user): bool
    {
        return $this->twoFactorRepository->disableForUser($user);
    }

    /**
     * Reset 2FA configuration
     */
    public function reset(User $user): bool
    {
        return $this->twoFactorRepository->resetForUser($user);
    }

    /**
     * Check if user has 2FA enabled
     */
    public function isEnabled(User $user): bool
    {
        $twoFactor = $this->twoFactorRepository->findByUser($user);
        return $twoFactor && $twoFactor->isEnabled();
    }

    /**
     * Get 2FA configuration for user
     */
    public function getConfiguration(User $user): ?TwoFactorAuth
    {
        return $this->twoFactorRepository->findByUser($user);
    }

    /**
     * Get QR code data URL
     */
    public function getQrCodeDataUrl(TwoFactorAuth $twoFactor): ?string
    {
        $qrUrl = $twoFactor->getQrCodeUrl();
        
        if (!$qrUrl) {
            return null;
        }
        
        // You would typically use a QR code library here
        // For now, return the URL itself
        return $qrUrl;
    }

    /**
     * Generate TOTP secret
     */
    private function generateSecret(): string
    {
        // Generate a 32-character base32 secret
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        
        for ($i = 0; $i < 32; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $secret;
    }

    /**
     * Verify TOTP code against secret
     */
    private function verifyTOTP(string $secret, string $code, int $window = 1): bool
    {
        $time = time();
        $timeStep = 30; // TOTP time step in seconds
        
        // Check current time slot and adjacent time slots (window)
        for ($i = -$window; $i <= $window; $i++) {
            $timeSlot = intval(($time + ($i * $timeStep)) / $timeStep);
            $expectedCode = $this->generateTOTP($secret, $timeSlot);
            
            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Generate TOTP code for given secret and time slot
     */
    private function generateTOTP(string $secret, int $timeSlot): string
    {
        // Convert base32 secret to binary
        $binarySecret = $this->base32Decode($secret);
        
        // Pack time slot as 64-bit big-endian
        $time = pack('N*', 0) . pack('N*', $timeSlot);
        
        // Generate HMAC
        $hash = hash_hmac('sha1', $time, $binarySecret, true);
        
        // Dynamic truncation
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Decode base32 string
     */
    private function base32Decode(string $data): string
    {
        $data = strtoupper($data);
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $charMap = array_flip(str_split($chars));
        
        $binary = '';
        $buffer = 0;
        $bufferLength = 0;
        
        for ($i = 0; $i < strlen($data); $i++) {
            $char = $data[$i];
            
            if (!isset($charMap[$char])) {
                continue; // Skip invalid characters
            }
            
            $buffer = ($buffer << 5) | $charMap[$char];
            $bufferLength += 5;
            
            if ($bufferLength >= 8) {
                $binary .= chr(($buffer >> ($bufferLength - 8)) & 0xFF);
                $bufferLength -= 8;
            }
        }
        
        return $binary;
    }

    /**
     * Get adoption statistics
     */
    public function getAdoptionStatistics(): array
    {
        return $this->twoFactorRepository->getAdoptionStatistics();
    }

    /**
     * Get usage statistics
     */
    public function getUsageStatistics(): array
    {
        return $this->twoFactorRepository->getUsageStatistics();
    }

    /**
     * Get configurations requiring attention
     */
    public function getConfigurationsRequiringAttention(): array
    {
        return $this->twoFactorRepository->findConfigurationsRequiringAttention();
    }

    /**
     * Cleanup old configurations
     */
    public function cleanupOldConfigurations(int $daysOld = 30): int
    {
        $cutoffDate = new \DateTimeImmutable('-' . $daysOld . ' days');
        return $this->twoFactorRepository->cleanupOldConfigurations($cutoffDate);
    }

    /**
     * Validate backup code format
     */
    public function isValidBackupCodeFormat(string $code): bool
    {
        return preg_match('/^[A-F0-9]{8}$/i', $code) === 1;
    }

    /**
     * Validate TOTP code format
     */
    public function isValidTOTPFormat(string $code): bool
    {
        return preg_match('/^\d{6}$/', $code) === 1;
    }

    /**
     * Get remaining backup codes count for user
     */
    public function getRemainingBackupCodesCount(User $user): int
    {
        $twoFactor = $this->twoFactorRepository->findByUser($user);
        return $twoFactor ? $twoFactor->getRemainingBackupCodesCount() : 0;
    }

    /**
     * Check if user needs to regenerate backup codes
     */
    public function needsBackupCodeRegeneration(User $user, int $threshold = 2): bool
    {
        return $this->getRemainingBackupCodesCount($user) <= $threshold;
    }
}