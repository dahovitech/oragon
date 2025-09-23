<?php

namespace App\Service\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticatorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

class TwoFactorService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GoogleAuthenticatorInterface $googleAuthenticator,
        private TotpAuthenticatorInterface $totpAuthenticator
    ) {
    }

    /**
     * Enable 2FA for a user with Google Authenticator
     */
    public function enableGoogleAuthenticator(User $user): string
    {
        $secret = $this->googleAuthenticator->generateSecret();
        $user->setGoogleAuthenticatorSecret($secret);
        $user->setTwoFactorEnabled(true);
        
        $this->entityManager->flush();
        
        return $secret;
    }

    /**
     * Enable 2FA for a user with TOTP
     */
    public function enableTotp(User $user): string
    {
        $secret = $this->totpAuthenticator->generateSecret();
        $user->setTotpSecret($secret);
        $user->setTwoFactorEnabled(true);
        
        $this->entityManager->flush();
        
        return $secret;
    }

    /**
     * Disable 2FA for a user
     */
    public function disable2FA(User $user): void
    {
        $user->setGoogleAuthenticatorSecret(null);
        $user->setTotpSecret(null);
        $user->setTwoFactorEnabled(false);
        $user->setBackupCodes([]);
        
        $this->entityManager->flush();
    }

    /**
     * Generate QR code for Google Authenticator setup
     */
    public function generateQrCode(User $user): string
    {
        $qrCodeUrl = $this->googleAuthenticator->getQRContent($user);
        
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($qrCodeUrl)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->size(200)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->build();

        return $result->getDataUri();
    }

    /**
     * Generate backup codes for a user
     */
    public function generateBackupCodes(User $user, int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->generateRandomCode();
        }
        
        $user->setBackupCodes($codes);
        $this->entityManager->flush();
        
        return $codes;
    }

    /**
     * Verify a 2FA code
     */
    public function verifyCode(User $user, string $code): bool
    {
        // Check Google Authenticator
        if ($user->isGoogleAuthenticatorEnabled()) {
            if ($this->googleAuthenticator->checkCode($user, $code)) {
                return true;
            }
        }

        // Check TOTP
        if ($user->isTotpAuthenticationEnabled()) {
            if ($this->totpAuthenticator->checkCode($user, $code)) {
                return true;
            }
        }

        // Check backup codes
        if ($user->isBackupCode($code)) {
            $user->invalidateBackupCode($code);
            $this->entityManager->flush();
            return true;
        }

        return false;
    }

    /**
     * Check if user has 2FA enabled
     */
    public function is2FAEnabled(User $user): bool
    {
        return $user->isTwoFactorEnabled() && 
               ($user->isGoogleAuthenticatorEnabled() || $user->isTotpAuthenticationEnabled());
    }

    /**
     * Get 2FA status for a user
     */
    public function get2FAStatus(User $user): array
    {
        return [
            'enabled' => $this->is2FAEnabled($user),
            'google_authenticator' => $user->isGoogleAuthenticatorEnabled(),
            'totp' => $user->isTotpAuthenticationEnabled(),
            'backup_codes_count' => count($user->getBackupCodes()),
            'trusted_device_version' => $user->getTrustedTokenVersion(),
        ];
    }

    /**
     * Generate a random backup code
     */
    private function generateRandomCode(int $length = 8): string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $code;
    }
}