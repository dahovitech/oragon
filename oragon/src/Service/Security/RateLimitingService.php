<?php

namespace App\Service\Security;

use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

class RateLimitingService
{
    public function __construct(
        private RateLimiterFactory $loginLimiterFactory,
        private RateLimiterFactory $apiLimiterFactory,
        private RateLimiterFactory $generalLimiterFactory,
        private SecurityAuditService $auditService
    ) {
    }

    /**
     * Check and apply login rate limiting
     */
    public function checkLoginRateLimit(Request $request, string $identifier): ?Response
    {
        $ipLimiter = $this->loginLimiterFactory->create($request->getClientIp());
        $userLimiter = $this->loginLimiterFactory->create($identifier);

        // Check IP-based limiting
        $ipLimit = $ipLimiter->consume(1);
        if (!$ipLimit->isAccepted()) {
            $this->auditService->logRateLimitExceeded('login_ip', [
                'ip' => $request->getClientIp(),
                'identifier' => $identifier,
                'retry_after' => $ipLimit->getRetryAfter()->getTimestamp(),
            ]);

            return new Response(
                'Too many login attempts from this IP. Try again later.',
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => $ipLimit->getRetryAfter()->getTimestamp()]
            );
        }

        // Check user-based limiting
        $userLimit = $userLimiter->consume(1);
        if (!$userLimit->isAccepted()) {
            $this->auditService->logRateLimitExceeded('login_user', [
                'ip' => $request->getClientIp(),
                'identifier' => $identifier,
                'retry_after' => $userLimit->getRetryAfter()->getTimestamp(),
            ]);

            return new Response(
                'Too many login attempts for this account. Try again later.',
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => $userLimit->getRetryAfter()->getTimestamp()]
            );
        }

        return null; // No rate limiting applied
    }

    /**
     * Check and apply API rate limiting
     */
    public function checkApiRateLimit(Request $request, ?UserInterface $user = null): ?Response
    {
        $identifier = $user ? $user->getUserIdentifier() : $request->getClientIp();
        $limiter = $this->apiLimiterFactory->create($identifier);

        $limit = $limiter->consume(1);
        if (!$limit->isAccepted()) {
            $this->auditService->logRateLimitExceeded('api', [
                'ip' => $request->getClientIp(),
                'user' => $user ? $user->getUserIdentifier() : null,
                'endpoint' => $request->getPathInfo(),
                'retry_after' => $limit->getRetryAfter()->getTimestamp(),
            ]);

            return new Response(
                json_encode([
                    'error' => 'Rate limit exceeded',
                    'message' => 'Too many API requests. Please slow down.',
                    'retry_after' => $limit->getRetryAfter()->getTimestamp(),
                ]),
                Response::HTTP_TOO_MANY_REQUESTS,
                [
                    'Content-Type' => 'application/json',
                    'Retry-After' => $limit->getRetryAfter()->getTimestamp(),
                    'X-RateLimit-Limit' => $limit->getLimit(),
                    'X-RateLimit-Remaining' => $limit->getRemainingTokens(),
                    'X-RateLimit-Reset' => $limit->getRetryAfter()->getTimestamp(),
                ]
            );
        }

        return null; // No rate limiting applied
    }

    /**
     * Check and apply general rate limiting
     */
    public function checkGeneralRateLimit(Request $request): ?Response
    {
        $limiter = $this->generalLimiterFactory->create($request->getClientIp());

        $limit = $limiter->consume(1);
        if (!$limit->isAccepted()) {
            $this->auditService->logRateLimitExceeded('general', [
                'ip' => $request->getClientIp(),
                'endpoint' => $request->getPathInfo(),
                'retry_after' => $limit->getRetryAfter()->getTimestamp(),
            ]);

            return new Response(
                'Rate limit exceeded. Please slow down.',
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => $limit->getRetryAfter()->getTimestamp()]
            );
        }

        return null; // No rate limiting applied
    }

    /**
     * Reset rate limits for a user (admin function)
     */
    public function resetUserRateLimits(string $identifier): void
    {
        // Reset login limits
        $loginLimiter = $this->loginLimiterFactory->create($identifier);
        $loginLimiter->reset();

        // Reset API limits
        $apiLimiter = $this->apiLimiterFactory->create($identifier);
        $apiLimiter->reset();
    }

    /**
     * Reset rate limits for an IP address (admin function)
     */
    public function resetIpRateLimits(string $ipAddress): void
    {
        // Reset login limits
        $loginLimiter = $this->loginLimiterFactory->create($ipAddress);
        $loginLimiter->reset();

        // Reset general limits
        $generalLimiter = $this->generalLimiterFactory->create($ipAddress);
        $generalLimiter->reset();
    }

    /**
     * Get rate limit status for monitoring
     */
    public function getRateLimitStatus(string $identifier, string $type = 'api'): array
    {
        $factory = match($type) {
            'login' => $this->loginLimiterFactory,
            'api' => $this->apiLimiterFactory,
            'general' => $this->generalLimiterFactory,
            default => $this->apiLimiterFactory,
        };

        $limiter = $factory->create($identifier);
        $limit = $limiter->consume(0); // Don't actually consume a token

        return [
            'identifier' => $identifier,
            'type' => $type,
            'limit' => $limit->getLimit(),
            'remaining' => $limit->getRemainingTokens(),
            'reset_time' => $limit->getRetryAfter()?->getTimestamp(),
            'is_available' => $limit->isAccepted(),
        ];
    }
}