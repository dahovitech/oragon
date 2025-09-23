<?php

namespace App\Service;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Cache\CacheItemPoolInterface;

class RateLimitService
{
    private CacheItemPoolInterface $cache;
    private array $limits;

    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
        $this->initializeLimits();
    }

    /**
     * Initialize rate limiting rules
     */
    private function initializeLimits(): void
    {
        $this->limits = [
            'login' => [
                'requests' => 5,
                'window' => 300, // 5 minutes
                'block_duration' => 900, // 15 minutes
            ],
            'api' => [
                'requests' => 100,
                'window' => 3600, // 1 hour
                'block_duration' => 3600,
            ],
            'password_reset' => [
                'requests' => 3,
                'window' => 3600, // 1 hour
                'block_duration' => 3600,
            ],
            'contact_form' => [
                'requests' => 5,
                'window' => 3600, // 1 hour
                'block_duration' => 3600,
            ],
            'two_factor' => [
                'requests' => 10,
                'window' => 300, // 5 minutes
                'block_duration' => 900,
            ],
            'global' => [
                'requests' => 1000,
                'window' => 3600, // 1 hour
                'block_duration' => 3600,
            ],
        ];
    }

    /**
     * Check if request is rate limited
     */
    public function isRateLimited(Request $request, string $limitType = 'global', ?string $identifier = null): array
    {
        if (!isset($this->limits[$limitType])) {
            throw new \InvalidArgumentException("Unknown limit type: {$limitType}");
        }

        $identifier = $identifier ?: $this->getIdentifier($request);
        $limit = $this->limits[$limitType];
        
        // Check if currently blocked
        $blockKey = "rate_limit:block:{$limitType}:{$identifier}";
        $blockItem = $this->cache->getItem($blockKey);
        
        if ($blockItem->isHit()) {
            $blockData = $blockItem->get();
            return [
                'limited' => true,
                'remaining' => 0,
                'reset_time' => $blockData['reset_time'],
                'retry_after' => $blockData['reset_time'] - time(),
                'reason' => 'blocked',
            ];
        }

        // Check current usage
        $usageKey = "rate_limit:usage:{$limitType}:{$identifier}";
        $usageItem = $this->cache->getItem($usageKey);
        
        $currentUsage = $usageItem->isHit() ? $usageItem->get() : [
            'count' => 0,
            'window_start' => time(),
        ];

        // Reset window if expired
        if (time() - $currentUsage['window_start'] >= $limit['window']) {
            $currentUsage = [
                'count' => 0,
                'window_start' => time(),
            ];
        }

        $currentUsage['count']++;
        
        // Check if limit exceeded
        if ($currentUsage['count'] > $limit['requests']) {
            // Block the identifier
            $resetTime = time() + $limit['block_duration'];
            $blockItem->set(['reset_time' => $resetTime]);
            $blockItem->expiresAt(new \DateTime('@' . $resetTime));
            $this->cache->save($blockItem);
            
            // Log rate limit violation
            $this->logRateLimitViolation($request, $limitType, $identifier, $currentUsage['count']);
            
            return [
                'limited' => true,
                'remaining' => 0,
                'reset_time' => $resetTime,
                'retry_after' => $limit['block_duration'],
                'reason' => 'limit_exceeded',
            ];
        }

        // Update usage
        $usageItem->set($currentUsage);
        $usageItem->expiresAfter($limit['window']);
        $this->cache->save($usageItem);

        return [
            'limited' => false,
            'remaining' => $limit['requests'] - $currentUsage['count'],
            'reset_time' => $currentUsage['window_start'] + $limit['window'],
            'retry_after' => 0,
            'reason' => 'allowed',
        ];
    }

    /**
     * Record a request attempt
     */
    public function recordAttempt(Request $request, string $limitType = 'global', ?string $identifier = null): array
    {
        return $this->isRateLimited($request, $limitType, $identifier);
    }

    /**
     * Check if rate limited and throw exception if needed
     */
    public function checkRateLimit(Request $request, string $limitType = 'global', ?string $identifier = null): void
    {
        $result = $this->isRateLimited($request, $limitType, $identifier);
        
        if ($result['limited']) {
            throw new RateLimitExceededException(
                "Rate limit exceeded for {$limitType}",
                $result['retry_after'],
                $result['reset_time']
            );
        }
    }

    /**
     * Get rate limit headers for response
     */
    public function getRateLimitHeaders(Request $request, string $limitType = 'global', ?string $identifier = null): array
    {
        $result = $this->isRateLimited($request, $limitType, $identifier);
        $limit = $this->limits[$limitType];
        
        return [
            'X-RateLimit-Limit' => $limit['requests'],
            'X-RateLimit-Remaining' => $result['remaining'],
            'X-RateLimit-Reset' => $result['reset_time'],
            'X-RateLimit-Window' => $limit['window'],
        ];
    }

    /**
     * Clear rate limit for identifier
     */
    public function clearRateLimit(string $limitType, string $identifier): bool
    {
        $usageKey = "rate_limit:usage:{$limitType}:{$identifier}";
        $blockKey = "rate_limit:block:{$limitType}:{$identifier}";
        
        $this->cache->deleteItems([$usageKey, $blockKey]);
        
        return true;
    }

    /**
     * Clear all rate limits for identifier
     */
    public function clearAllRateLimits(string $identifier): bool
    {
        foreach (array_keys($this->limits) as $limitType) {
            $this->clearRateLimit($limitType, $identifier);
        }
        
        return true;
    }

    /**
     * Get current usage statistics
     */
    public function getUsageStatistics(string $limitType, string $identifier): array
    {
        if (!isset($this->limits[$limitType])) {
            throw new \InvalidArgumentException("Unknown limit type: {$limitType}");
        }

        $usageKey = "rate_limit:usage:{$limitType}:{$identifier}";
        $blockKey = "rate_limit:block:{$limitType}:{$identifier}";
        $limit = $this->limits[$limitType];
        
        $usageItem = $this->cache->getItem($usageKey);
        $blockItem = $this->cache->getItem($blockKey);
        
        $usage = $usageItem->isHit() ? $usageItem->get() : [
            'count' => 0,
            'window_start' => time(),
        ];
        
        $blocked = $blockItem->isHit();
        $blockData = $blocked ? $blockItem->get() : null;
        
        return [
            'limit_type' => $limitType,
            'identifier' => $identifier,
            'limit' => $limit['requests'],
            'window' => $limit['window'],
            'current_usage' => $usage['count'],
            'remaining' => max(0, $limit['requests'] - $usage['count']),
            'window_start' => $usage['window_start'],
            'window_end' => $usage['window_start'] + $limit['window'],
            'blocked' => $blocked,
            'block_expires' => $blockData ? $blockData['reset_time'] : null,
        ];
    }

    /**
     * Get identifier from request
     */
    private function getIdentifier(Request $request): string
    {
        // Use user ID if authenticated, otherwise IP address
        $user = $request->attributes->get('_security_user');
        
        if ($user && method_exists($user, 'getId')) {
            return 'user:' . $user->getId();
        }
        
        return 'ip:' . $this->getClientIp($request);
    }

    /**
     * Get client IP address
     */
    private function getClientIp(Request $request): string
    {
        $trustedProxies = ['127.0.0.1', '::1'];
        
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
     * Log rate limit violation
     */
    private function logRateLimitViolation(Request $request, string $limitType, string $identifier, int $attempts): void
    {
        $logData = [
            'event' => 'rate_limit_violation',
            'limit_type' => $limitType,
            'identifier' => $identifier,
            'attempts' => $attempts,
            'ip' => $this->getClientIp($request),
            'user_agent' => $request->headers->get('User-Agent'),
            'uri' => $request->getRequestUri(),
            'method' => $request->getMethod(),
            'timestamp' => date('c'),
        ];
        
        // Log to error log or security log
        error_log('SECURITY: Rate limit violation - ' . json_encode($logData));
    }

    /**
     * Get all configured limits
     */
    public function getLimits(): array
    {
        return $this->limits;
    }

    /**
     * Update limit configuration
     */
    public function updateLimit(string $limitType, int $requests, int $window, int $blockDuration): void
    {
        $this->limits[$limitType] = [
            'requests' => $requests,
            'window' => $window,
            'block_duration' => $blockDuration,
        ];
    }

    /**
     * Get global rate limit statistics
     */
    public function getGlobalStatistics(): array
    {
        // This would typically aggregate statistics from all rate limits
        // For now, return basic info about configured limits
        return [
            'configured_limits' => count($this->limits),
            'limit_types' => array_keys($this->limits),
            'total_limits' => array_sum(array_column($this->limits, 'requests')),
        ];
    }

    /**
     * Check if IP is in whitelist
     */
    public function isWhitelisted(string $ip): bool
    {
        $whitelist = [
            '127.0.0.1',
            '::1',
            // Add more IPs as needed
        ];
        
        return in_array($ip, $whitelist);
    }

    /**
     * Check if IP is in blacklist
     */
    public function isBlacklisted(string $ip): bool
    {
        $blacklistKey = "rate_limit:blacklist:{$ip}";
        $blacklistItem = $this->cache->getItem($blacklistKey);
        
        return $blacklistItem->isHit();
    }

    /**
     * Add IP to blacklist
     */
    public function addToBlacklist(string $ip, int $duration = 86400): void
    {
        $blacklistKey = "rate_limit:blacklist:{$ip}";
        $blacklistItem = $this->cache->getItem($blacklistKey);
        
        $blacklistItem->set(true);
        $blacklistItem->expiresAfter($duration);
        $this->cache->save($blacklistItem);
    }

    /**
     * Remove IP from blacklist
     */
    public function removeFromBlacklist(string $ip): void
    {
        $blacklistKey = "rate_limit:blacklist:{$ip}";
        $this->cache->deleteItem($blacklistKey);
    }
}

/**
 * Rate limit exception
 */
class RateLimitExceededException extends \Exception
{
    private int $retryAfter;
    private int $resetTime;

    public function __construct(string $message, int $retryAfter, int $resetTime)
    {
        parent::__construct($message);
        $this->retryAfter = $retryAfter;
        $this->resetTime = $resetTime;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public function getResetTime(): int
    {
        return $this->resetTime;
    }
}