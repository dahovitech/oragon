<?php

namespace App\Service;

use App\Entity\SecurityEvent;
use App\Entity\User;
use App\Repository\SecurityEventRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SecurityAuditService
{
    private SecurityEventRepository $securityEventRepository;
    private EntityManagerInterface $entityManager;
    private TokenStorageInterface $tokenStorage;
    private NotificationService $notificationService;
    private array $config;

    public function __construct(
        SecurityEventRepository $securityEventRepository,
        EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage,
        NotificationService $notificationService
    ) {
        $this->securityEventRepository = $securityEventRepository;
        $this->entityManager = $entityManager;
        $this->tokenStorage = $tokenStorage;
        $this->notificationService = $notificationService;
        $this->initializeConfig();
    }

    /**
     * Initialize security configuration
     */
    private function initializeConfig(): void
    {
        $this->config = [
            'alert_on_critical' => true,
            'alert_on_multiple_failures' => true,
            'failed_login_threshold' => 5,
            'failed_login_window' => 3600, // 1 hour
            'suspicious_ip_threshold' => 10,
            'auto_block_threshold' => 20,
            'notification_emails' => ['security@example.com'],
        ];
    }

    /**
     * Log a security event
     */
    public function logEvent(
        string $eventType,
        string $description,
        string $severity = 'info',
        ?User $user = null,
        ?Request $request = null,
        ?array $contextData = null
    ): SecurityEvent {
        $event = new SecurityEvent();
        $event->setEventType($eventType);
        $event->setDescription($description);
        $event->setSeverity($severity);
        $event->setUser($user);
        
        if ($request) {
            $event->setIpAddress($this->getClientIp($request));
            $event->setUserAgent($request->headers->get('User-Agent'));
            $event->setRequestUri($request->getRequestUri());
            $event->setRequestMethod($request->getMethod());
        }
        
        if ($contextData) {
            $event->setContextData($contextData);
        }

        // Add metadata
        $event->setMetadata([
            'timestamp' => time(),
            'session_id' => session_id(),
            'php_session' => session_status() === PHP_SESSION_ACTIVE,
        ]);

        $this->securityEventRepository->save($event, true);

        // Handle critical events
        if ($event->isCritical()) {
            $this->handleCriticalEvent($event);
        }

        // Check for patterns and automated responses
        $this->checkSecurityPatterns($event);

        return $event;
    }

    /**
     * Log login attempt
     */
    public function logLoginAttempt(string $email, bool $success, ?Request $request = null, ?User $user = null): SecurityEvent
    {
        $eventType = $success ? 'login_success' : 'login_failure';
        $description = $success 
            ? "Connexion réussie pour {$email}" 
            : "Échec de connexion pour {$email}";
        
        $severity = $success ? 'info' : 'medium';
        
        $contextData = [
            'email' => $email,
            'success' => $success,
        ];

        $event = $this->logEvent($eventType, $description, $severity, $user, $request, $contextData);

        // Check for brute force attempts
        if (!$success) {
            $this->checkBruteForceAttempt($email, $request);
        }

        return $event;
    }

    /**
     * Log 2FA event
     */
    public function logTwoFactorEvent(User $user, string $action, bool $success, ?Request $request = null): SecurityEvent
    {
        $eventType = $success ? "two_factor_{$action}" : 'two_factor_failure';
        $description = $success 
            ? "2FA {$action} réussi pour {$user->getEmail()}"
            : "Échec 2FA {$action} pour {$user->getEmail()}";
        
        $severity = $success ? 'info' : 'medium';
        
        $contextData = [
            'action' => $action,
            'success' => $success,
            'user_id' => $user->getId(),
        ];

        return $this->logEvent($eventType, $description, $severity, $user, $request, $contextData);
    }

    /**
     * Log permission denied
     */
    public function logPermissionDenied(string $resource, ?User $user = null, ?Request $request = null): SecurityEvent
    {
        $description = $user 
            ? "Accès refusé à {$resource} pour {$user->getEmail()}"
            : "Accès refusé à {$resource} pour utilisateur anonyme";
        
        $contextData = [
            'resource' => $resource,
            'user_id' => $user?->getId(),
        ];

        return $this->logEvent('permission_denied', $description, 'medium', $user, $request, $contextData);
    }

    /**
     * Log suspicious activity
     */
    public function logSuspiciousActivity(string $description, string $reason, ?User $user = null, ?Request $request = null): SecurityEvent
    {
        $contextData = [
            'reason' => $reason,
            'detected_at' => date('c'),
        ];

        return $this->logEvent('suspicious_activity', $description, 'high', $user, $request, $contextData);
    }

    /**
     * Log rate limit exceeded
     */
    public function logRateLimitExceeded(string $limitType, string $identifier, ?Request $request = null): SecurityEvent
    {
        $description = "Limite de taux dépassée pour {$limitType} ({$identifier})";
        
        $contextData = [
            'limit_type' => $limitType,
            'identifier' => $identifier,
        ];

        return $this->logEvent('rate_limit_exceeded', $description, 'medium', null, $request, $contextData);
    }

    /**
     * Log CSRF token mismatch
     */
    public function logCSRFViolation(?User $user = null, ?Request $request = null): SecurityEvent
    {
        $description = "Jeton CSRF invalide détecté";
        
        return $this->logEvent('csrf_token_mismatch', $description, 'medium', $user, $request);
    }

    /**
     * Log potential attack
     */
    public function logPotentialAttack(string $attackType, string $payload, ?Request $request = null): SecurityEvent
    {
        $description = "Tentative d'attaque {$attackType} détectée";
        
        $contextData = [
            'attack_type' => $attackType,
            'payload' => substr($payload, 0, 1000), // Limit payload size
            'blocked' => true,
        ];

        return $this->logEvent($attackType . '_attempt', $description, 'high', null, $request, $contextData);
    }

    /**
     * Handle critical security events
     */
    private function handleCriticalEvent(SecurityEvent $event): void
    {
        if (!$this->config['alert_on_critical']) {
            return;
        }

        // Send immediate notification
        $this->sendSecurityAlert($event);
        
        // Log to system
        error_log("CRITICAL SECURITY EVENT: {$event->getEventType()} - {$event->getDescription()}");
    }

    /**
     * Check for brute force attempts
     */
    private function checkBruteForceAttempt(string $email, ?Request $request): void
    {
        if (!$request) {
            return;
        }

        $since = new \DateTimeImmutable('-' . $this->config['failed_login_window'] . ' seconds');
        $failures = $this->securityEventRepository->findFailedLoginAttempts($since, $this->getClientIp($request));

        if (count($failures) >= $this->config['failed_login_threshold']) {
            $this->logSuspiciousActivity(
                "Tentative de force brute détectée",
                "Trop de tentatives de connexion échouées",
                null,
                $request
            );
        }
    }

    /**
     * Check security patterns for automated responses
     */
    private function checkSecurityPatterns(SecurityEvent $event): void
    {
        $ipAddress = $event->getIpAddress();
        
        if (!$ipAddress) {
            return;
        }

        // Check for suspicious IP activity
        $since = new \DateTimeImmutable('-24 hours');
        $ipEvents = $this->securityEventRepository->findByIpAddress($ipAddress);
        
        $suspiciousEvents = array_filter($ipEvents, function($e) use ($since) {
            return $e->getCreatedAt() >= $since && 
                   in_array($e->getEventType(), [
                       'login_failure',
                       'permission_denied',
                       'suspicious_activity',
                       'csrf_token_mismatch',
                       'sql_injection_attempt',
                       'xss_attempt'
                   ]);
        });

        if (count($suspiciousEvents) >= $this->config['suspicious_ip_threshold']) {
            $this->flagSuspiciousIp($ipAddress, count($suspiciousEvents));
        }
    }

    /**
     * Flag suspicious IP address
     */
    private function flagSuspiciousIp(string $ipAddress, int $eventCount): void
    {
        $this->logSuspiciousActivity(
            "IP {$ipAddress} marquée comme suspecte",
            "Trop d'événements de sécurité ({$eventCount})"
        );

        // Auto-block if threshold exceeded
        if ($eventCount >= $this->config['auto_block_threshold']) {
            // Here you would implement IP blocking logic
            $this->logEvent(
                'ip_blocked',
                "IP {$ipAddress} bloquée automatiquement",
                'critical',
                null,
                null,
                ['ip_address' => $ipAddress, 'event_count' => $eventCount]
            );
        }
    }

    /**
     * Send security alert
     */
    private function sendSecurityAlert(SecurityEvent $event): void
    {
        try {
            // Send notification to security team
            foreach ($this->config['notification_emails'] as $email) {
                $this->notificationService->sendToEmail(
                    $email,
                    'security_alert',
                    'Alerte de Sécurité Critique',
                    "Un événement de sécurité critique a été détecté : {$event->getDescription()}",
                    [
                        'event_type' => $event->getEventType(),
                        'severity' => $event->getSeverity(),
                        'ip_address' => $event->getIpAddress(),
                        'user_agent' => $event->getUserAgent(),
                        'timestamp' => $event->getCreatedAt()->format('Y-m-d H:i:s'),
                        'action_url' => '/admin/security/events/' . $event->getId(),
                        'action_text' => 'Voir les détails',
                    ]
                );
            }
        } catch (\Exception $e) {
            error_log("Failed to send security alert: " . $e->getMessage());
        }
    }

    /**
     * Get security statistics
     */
    public function getSecurityStatistics(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->securityEventRepository->getSecurityStatistics($from, $to);
    }

    /**
     * Get security trends
     */
    public function getSecurityTrends(int $days = 30): array
    {
        return $this->securityEventRepository->getSecurityTrends($days);
    }

    /**
     * Get events requiring attention
     */
    public function getEventsRequiringAttention(): array
    {
        return $this->securityEventRepository->findEventsRequiringAttention();
    }

    /**
     * Resolve security events
     */
    public function resolveEvents(array $eventIds, string $resolution, ?int $resolvedBy = null): int
    {
        return $this->securityEventRepository->markAsResolved($eventIds, $resolution, $resolvedBy);
    }

    /**
     * Clean old security events
     */
    public function cleanOldEvents(int $daysOld = 90): int
    {
        $before = new \DateTimeImmutable('-' . $daysOld . ' days');
        return $this->securityEventRepository->cleanOldEvents($before);
    }

    /**
     * Get client IP address
     */
    private function getClientIp(Request $request): string
    {
        // Check for IP from shared internet
        if (!empty($request->server->get('HTTP_CLIENT_IP'))) {
            return $request->server->get('HTTP_CLIENT_IP');
        }
        // Check for IP passed from proxy
        elseif (!empty($request->server->get('HTTP_X_FORWARDED_FOR'))) {
            $ips = explode(',', $request->server->get('HTTP_X_FORWARDED_FOR'));
            return trim($ips[0]);
        }
        // Check for IP from remote address
        else {
            return $request->server->get('REMOTE_ADDR', '0.0.0.0');
        }
    }

    /**
     * Update configuration
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get current configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Check if IP is suspicious
     */
    public function isSuspiciousIp(string $ipAddress): bool
    {
        $since = new \DateTimeImmutable('-24 hours');
        $events = $this->securityEventRepository->findByIpAddress($ipAddress);
        
        $suspiciousEvents = array_filter($events, function($e) use ($since) {
            return $e->getCreatedAt() >= $since && $e->isCritical();
        });

        return count($suspiciousEvents) >= $this->config['suspicious_ip_threshold'];
    }

    /**
     * Generate security report
     */
    public function generateSecurityReport(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $statistics = $this->getSecurityStatistics($from, $to);
        $trends = $this->getSecurityTrends(($to->getTimestamp() - $from->getTimestamp()) / 86400);
        $eventTypes = $this->securityEventRepository->getEventTypeStatistics($from, $to);
        $suspiciousIps = $this->securityEventRepository->findSuspiciousIps($from);

        return [
            'period' => [
                'from' => $from->format('Y-m-d H:i:s'),
                'to' => $to->format('Y-m-d H:i:s'),
            ],
            'statistics' => $statistics,
            'trends' => $trends,
            'event_types' => $eventTypes,
            'suspicious_ips' => $suspiciousIps,
            'critical_events' => $this->securityEventRepository->findBySeverity('critical'),
            'unresolved_events' => $this->securityEventRepository->findCriticalUnresolvedEvents(),
        ];
    }
}