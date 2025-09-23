<?php

namespace App\EventListener\Security;

use App\Service\Security\RateLimitingService;
use App\Service\Security\SecurityAuditService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;

#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest', priority: 100)]
class RateLimitingListener
{
    public function __construct(
        private RateLimitingService $rateLimitingService,
        private SecurityAuditService $auditService,
        private Security $security
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Skip rate limiting for assets and profiler
        if ($this->shouldSkipRateLimit($path)) {
            return;
        }

        // Apply different rate limiting based on the endpoint
        $response = null;

        // API endpoints
        if (str_starts_with($path, '/api/')) {
            $user = $this->security->getUser();
            $response = $this->rateLimitingService->checkApiRateLimit($request, $user);
        }
        // Login endpoints
        elseif (in_array($path, ['/login', '/security/login', '/2fa/login'])) {
            $identifier = $this->getLoginIdentifier($request);
            if ($identifier) {
                $response = $this->rateLimitingService->checkLoginRateLimit($request, $identifier);
            }
        }
        // General endpoints
        else {
            $response = $this->rateLimitingService->checkGeneralRateLimit($request);
        }

        if ($response !== null) {
            $event->setResponse($response);
            $event->stopPropagation();
        }
    }

    /**
     * Determine if rate limiting should be skipped for this path
     */
    private function shouldSkipRateLimit(string $path): bool
    {
        $skipPatterns = [
            '/css/',
            '/js/',
            '/images/',
            '/favicon.ico',
            '/_profiler',
            '/_wdt',
            '/build/',
            '/assets/',
        ];

        foreach ($skipPatterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract login identifier from request
     */
    private function getLoginIdentifier($request): ?string
    {
        // Try to get email from POST data
        $email = $request->request->get('email') ?? $request->request->get('_username');
        
        if ($email) {
            return $email;
        }

        // Fallback to IP address for rate limiting
        return $request->getClientIp();
    }
}