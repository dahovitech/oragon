<?php

namespace App\EventListener;

use App\Service\RateLimitService;
use App\Service\SecurityAuditService;
use App\Service\RateLimitExceededException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class SecurityEventListener
{
    private RateLimitService $rateLimitService;
    private SecurityAuditService $securityAuditService;
    private TokenStorageInterface $tokenStorage;
    private array $config;

    public function __construct(
        RateLimitService $rateLimitService,
        SecurityAuditService $securityAuditService,
        TokenStorageInterface $tokenStorage
    ) {
        $this->rateLimitService = $rateLimitService;
        $this->securityAuditService = $securityAuditService;
        $this->tokenStorage = $tokenStorage;
        $this->initializeConfig();
    }

    private function initializeConfig(): void
    {
        $this->config = [
            'rate_limiting_enabled' => true,
            'security_headers_enabled' => true,
            'csrf_protection_enabled' => true,
            'suspicious_patterns' => [
                'sql_injection' => [
                    'union\s+select',
                    'drop\s+table',
                    'insert\s+into',
                    'delete\s+from',
                    '1\s*=\s*1',
                    'or\s+1\s*=\s*1',
                ],
                'xss' => [
                    '<script',
                    'javascript:',
                    'onload\s*=',
                    'onerror\s*=',
                    'onclick\s*=',
                ],
                'path_traversal' => [
                    '\.\.\/',
                    '\.\.\\\\',
                    '/etc/passwd',
                    '/proc/self/environ',
                ],
            ],
        ];
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 256)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        try {
            // Apply security checks
            $this->checkSuspiciousPatterns($request);
            $this->applyRateLimiting($request);
            $this->checkBlacklist($request);
            
        } catch (RateLimitExceededException $e) {
            $this->handleRateLimitExceeded($event, $e);
        } catch (SecurityViolationException $e) {
            $this->handleSecurityViolation($event, $e);
        }
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: 0)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Add security headers
        if ($this->config['security_headers_enabled']) {
            $this->addSecurityHeaders($response);
        }

        // Add rate limit headers
        if ($this->config['rate_limiting_enabled']) {
            $this->addRateLimitHeaders($request, $response);
        }
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 128)]
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Log security-related exceptions
        if ($exception instanceof RateLimitExceededException) {
            $this->securityAuditService->logRateLimitExceeded(
                'unknown',
                $this->getClientIp($request),
                $request
            );
        } elseif ($exception instanceof SecurityViolationException) {
            $this->securityAuditService->logSuspiciousActivity(
                $exception->getMessage(),
                $exception->getReason(),
                $this->getCurrentUser(),
                $request
            );
        }
    }

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $event->getRequest();

        $this->securityAuditService->logLoginAttempt(
            $user->getUserIdentifier(),
            true,
            $request,
            $user
        );
    }

    #[AsEventListener(event: LoginFailureEvent::class)]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $email = $request->request->get('_username', 'unknown');

        $this->securityAuditService->logLoginAttempt(
            $email,
            false,
            $request
        );
    }

    /**
     * Check for suspicious patterns in request
     */
    private function checkSuspiciousPatterns(Request $request): void
    {
        $content = $this->getRequestContent($request);
        
        foreach ($this->config['suspicious_patterns'] as $attackType => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match('/' . $pattern . '/i', $content)) {
                    $this->securityAuditService->logPotentialAttack(
                        $attackType,
                        $content,
                        $request
                    );
                    
                    throw new SecurityViolationException(
                        "Potential {$attackType} attack detected",
                        $attackType
                    );
                }
            }
        }
    }

    /**
     * Apply rate limiting
     */
    private function applyRateLimiting(Request $request): void
    {
        if (!$this->config['rate_limiting_enabled']) {
            return;
        }

        $route = $request->attributes->get('_route');
        $limitType = $this->determineLimitType($request, $route);

        $result = $this->rateLimitService->recordAttempt($request, $limitType);

        if ($result['limited']) {
            throw new RateLimitExceededException(
                "Rate limit exceeded for {$limitType}",
                $result['retry_after'],
                $result['reset_time']
            );
        }
    }

    /**
     * Check if IP is blacklisted
     */
    private function checkBlacklist(Request $request): void
    {
        $ip = $this->getClientIp($request);
        
        if ($this->rateLimitService->isBlacklisted($ip)) {
            $this->securityAuditService->logEvent(
                'blacklisted_access',
                "Accès tenté depuis IP blacklistée: {$ip}",
                'high',
                null,
                $request
            );
            
            throw new SecurityViolationException(
                "Access denied: IP blacklisted",
                'blacklisted_ip'
            );
        }
    }

    /**
     * Handle rate limit exceeded
     */
    private function handleRateLimitExceeded(RequestEvent $event, RateLimitExceededException $e): void
    {
        $response = new JsonResponse([
            'error' => 'Rate limit exceeded',
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $e->getRetryAfter(),
        ], Response::HTTP_TOO_MANY_REQUESTS);

        $response->headers->set('Retry-After', (string) $e->getRetryAfter());
        $response->headers->set('X-RateLimit-Reset', (string) $e->getResetTime());

        $event->setResponse($response);
    }

    /**
     * Handle security violation
     */
    private function handleSecurityViolation(RequestEvent $event, SecurityViolationException $e): void
    {
        $response = new JsonResponse([
            'error' => 'Security violation',
            'message' => 'Request blocked for security reasons.',
        ], Response::HTTP_FORBIDDEN);

        $event->setResponse($response);
    }

    /**
     * Add security headers to response
     */
    private function addSecurityHeaders(Response $response): void
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;",
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ];

        foreach ($headers as $name => $value) {
            if (!$response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }
    }

    /**
     * Add rate limit headers to response
     */
    private function addRateLimitHeaders(Request $request, Response $response): void
    {
        $route = $request->attributes->get('_route');
        $limitType = $this->determineLimitType($request, $route);
        
        $headers = $this->rateLimitService->getRateLimitHeaders($request, $limitType);
        
        foreach ($headers as $name => $value) {
            $response->headers->set($name, (string) $value);
        }
    }

    /**
     * Determine rate limit type based on request
     */
    private function determineLimitType(Request $request, ?string $route): string
    {
        // Login endpoints
        if ($route && str_contains($route, 'login')) {
            return 'login';
        }

        // API endpoints
        if ($route && str_contains($route, 'api')) {
            return 'api';
        }

        // Password reset
        if ($route && str_contains($route, 'password')) {
            return 'password_reset';
        }

        // Contact forms
        if ($route && str_contains($route, 'contact')) {
            return 'contact_form';
        }

        // 2FA endpoints
        if ($route && str_contains($route, '2fa') || str_contains($route, 'two_factor')) {
            return 'two_factor';
        }

        return 'global';
    }

    /**
     * Get request content for analysis
     */
    private function getRequestContent(Request $request): string
    {
        $content = [];
        
        // Query parameters
        $content[] = http_build_query($request->query->all());
        
        // POST data
        $content[] = http_build_query($request->request->all());
        
        // Request body
        $content[] = $request->getContent();
        
        // Headers (selected)
        $dangerousHeaders = ['User-Agent', 'Referer', 'X-Forwarded-For'];
        foreach ($dangerousHeaders as $header) {
            if ($request->headers->has($header)) {
                $content[] = $request->headers->get($header);
            }
        }

        return implode(' ', $content);
    }

    /**
     * Get client IP
     */
    private function getClientIp(Request $request): string
    {
        if (!empty($request->server->get('HTTP_CLIENT_IP'))) {
            return $request->server->get('HTTP_CLIENT_IP');
        } elseif (!empty($request->server->get('HTTP_X_FORWARDED_FOR'))) {
            $ips = explode(',', $request->server->get('HTTP_X_FORWARDED_FOR'));
            return trim($ips[0]);
        } else {
            return $request->server->get('REMOTE_ADDR', '0.0.0.0');
        }
    }

    /**
     * Get current user
     */
    private function getCurrentUser()
    {
        $token = $this->tokenStorage->getToken();
        return $token ? $token->getUser() : null;
    }
}

/**
 * Security violation exception
 */
class SecurityViolationException extends \Exception
{
    private string $reason;

    public function __construct(string $message, string $reason)
    {
        parent::__construct($message);
        $this->reason = $reason;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}