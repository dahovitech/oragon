<?php

namespace App\Service\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

class SecurityAuditService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private RequestStack $requestStack
    ) {
    }

    /**
     * Log a security event
     */
    public function logSecurityEvent(string $eventType, ?UserInterface $user = null, array $context = []): void
    {
        $request = $this->requestStack->getCurrentRequest();
        
        $logData = [
            'event_type' => $eventType,
            'user_id' => $user ? $user->getId() : null,
            'user_email' => $user ? $user->getUserIdentifier() : null,
            'ip_address' => $request ? $request->getClientIp() : null,
            'user_agent' => $request ? $request->headers->get('User-Agent') : null,
            'timestamp' => new \DateTimeImmutable(),
            'context' => $context,
        ];

        $this->logger->info('Security Event: ' . $eventType, $logData);
        
        // Store critical events in database
        if ($this->isCriticalEvent($eventType)) {
            $this->storeSecurityEvent($logData);
        }
    }

    /**
     * Log login attempt
     */
    public function logLoginAttempt(string $email, bool $success, array $context = []): void
    {
        $eventType = $success ? 'login_success' : 'login_failed';
        $this->logSecurityEvent($eventType, null, array_merge(['email' => $email], $context));
    }

    /**
     * Log 2FA event
     */
    public function log2FAEvent(User $user, string $eventType, array $context = []): void
    {
        $this->logSecurityEvent('2fa_' . $eventType, $user, $context);
    }

    /**
     * Log password change
     */
    public function logPasswordChange(User $user, array $context = []): void
    {
        $this->logSecurityEvent('password_changed', $user, $context);
    }

    /**
     * Log suspicious activity
     */
    public function logSuspiciousActivity(string $activity, ?UserInterface $user = null, array $context = []): void
    {
        $this->logSecurityEvent('suspicious_activity', $user, array_merge(['activity' => $activity], $context));
    }

    /**
     * Log rate limit exceeded
     */
    public function logRateLimitExceeded(string $limiterName, array $context = []): void
    {
        $this->logSecurityEvent('rate_limit_exceeded', null, array_merge(['limiter' => $limiterName], $context));
    }

    /**
     * Log security configuration change
     */
    public function logSecurityConfigChange(User $user, string $setting, $oldValue, $newValue): void
    {
        $this->logSecurityEvent('security_config_changed', $user, [
            'setting' => $setting,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }

    /**
     * Get security statistics
     */
    public function getSecurityStats(\DateTimeInterface $since = null): array
    {
        $since = $since ?: new \DateTimeImmutable('-30 days');
        
        // This would typically query a security_events table
        // For now, we return mock data
        return [
            'total_events' => 0,
            'login_attempts' => 0,
            'failed_logins' => 0,
            'successful_logins' => 0,
            '2fa_activations' => 0,
            'suspicious_activities' => 0,
            'rate_limit_violations' => 0,
            'period' => [
                'from' => $since->format('Y-m-d H:i:s'),
                'to' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * Check if an event type is critical and should be stored in database
     */
    private function isCriticalEvent(string $eventType): bool
    {
        $criticalEvents = [
            'login_failed',
            'suspicious_activity',
            'rate_limit_exceeded',
            '2fa_failed',
            'password_changed',
            'security_config_changed',
        ];

        return in_array($eventType, $criticalEvents);
    }

    /**
     * Store security event in database
     * Note: This would require a SecurityEvent entity in a full implementation
     */
    private function storeSecurityEvent(array $logData): void
    {
        // In a full implementation, you would create a SecurityEvent entity
        // and persist it to the database
        // For now, we just log it
        $this->logger->warning('Critical Security Event', $logData);
    }

    /**
     * Detect and log brute force attempts
     */
    public function detectBruteForce(string $email, string $ipAddress): bool
    {
        // In a real implementation, this would check recent failed attempts
        // from the same IP or for the same email
        
        // Mock implementation - always return false for now
        return false;
    }

    /**
     * Get recent security events for a user
     */
    public function getUserSecurityEvents(User $user, int $limit = 10): array
    {
        // In a real implementation, this would query the security_events table
        // For now, return empty array
        return [];
    }
}