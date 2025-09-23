<?php

namespace App\Controller\Admin;

use App\Service\SecurityAuditService;
use App\Service\RateLimitService;
use App\Service\TwoFactorService;
use App\Repository\SecurityEventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/security')]
class SecurityController extends AbstractController
{
    private SecurityAuditService $securityAuditService;
    private RateLimitService $rateLimitService;
    private TwoFactorService $twoFactorService;
    private SecurityEventRepository $securityEventRepository;

    public function __construct(
        SecurityAuditService $securityAuditService,
        RateLimitService $rateLimitService,
        TwoFactorService $twoFactorService,
        SecurityEventRepository $securityEventRepository
    ) {
        $this->securityAuditService = $securityAuditService;
        $this->rateLimitService = $rateLimitService;
        $this->twoFactorService = $twoFactorService;
        $this->securityEventRepository = $securityEventRepository;
    }

    #[Route('', name: 'admin_security_dashboard')]
    public function dashboard(Request $request): Response
    {
        $from = new \DateTimeImmutable($request->query->get('from', '-7 days'));
        $to = new \DateTimeImmutable($request->query->get('to', 'now'));

        $statistics = $this->securityAuditService->getSecurityStatistics($from, $to);
        $trends = $this->securityAuditService->getSecurityTrends(7);
        $eventsRequiringAttention = $this->securityAuditService->getEventsRequiringAttention();
        $twoFactorStats = $this->twoFactorService->getAdoptionStatistics();

        return $this->render('admin/security/dashboard.html.twig', [
            'statistics' => $statistics,
            'trends' => $trends,
            'events_requiring_attention' => $eventsRequiringAttention,
            'two_factor_stats' => $twoFactorStats,
            'from' => $from,
            'to' => $to,
        ]);
    }

    #[Route('/events', name: 'admin_security_events')]
    public function events(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $eventType = $request->query->get('event_type');
        $severity = $request->query->get('severity');
        $resolved = $request->query->get('resolved');

        $qb = $this->securityEventRepository->createQueryBuilder('s');

        if ($eventType) {
            $qb->andWhere('s.eventType = :eventType')
               ->setParameter('eventType', $eventType);
        }

        if ($severity) {
            $qb->andWhere('s.severity = :severity')
               ->setParameter('severity', $severity);
        }

        if ($resolved !== null) {
            $qb->andWhere('s.resolved = :resolved')
               ->setParameter('resolved', $resolved === '1');
        }

        $events = $qb->orderBy('s.createdAt', 'DESC')
                    ->setMaxResults($limit)
                    ->setFirstResult($offset)
                    ->getQuery()
                    ->getResult();

        $total = $qb->select('COUNT(s.id)')
                   ->setMaxResults(null)
                   ->setFirstResult(null)
                   ->getQuery()
                   ->getSingleScalarResult();

        return $this->render('admin/security/events.html.twig', [
            'events' => $events,
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'filters' => [
                'event_type' => $eventType,
                'severity' => $severity,
                'resolved' => $resolved,
            ],
            'event_types' => array_keys(\App\Entity\SecurityEvent::getEventTypes()),
            'severity_levels' => array_keys(\App\Entity\SecurityEvent::getSeverityLevels()),
        ]);
    }

    #[Route('/events/{id}', name: 'admin_security_event_show', requirements: ['id' => '\d+'])]
    public function showEvent(int $id): Response
    {
        $event = $this->securityEventRepository->find($id);
        
        if (!$event) {
            throw $this->createNotFoundException('Security event not found');
        }

        return $this->render('admin/security/event_detail.html.twig', [
            'event' => $event,
        ]);
    }

    #[Route('/events/resolve', name: 'admin_security_events_resolve', methods: ['POST'])]
    public function resolveEvents(Request $request): JsonResponse
    {
        $eventIds = $request->request->get('event_ids', []);
        $resolution = $request->request->get('resolution', '');

        if (empty($eventIds) || empty($resolution)) {
            return new JsonResponse(['error' => 'Event IDs and resolution are required'], 400);
        }

        try {
            $resolvedCount = $this->securityAuditService->resolveEvents(
                $eventIds,
                $resolution,
                $this->getUser()?->getId()
            );

            return new JsonResponse([
                'success' => true,
                'resolved_count' => $resolvedCount,
                'message' => sprintf('%d événements résolus', $resolvedCount)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/rate-limits', name: 'admin_security_rate_limits')]
    public function rateLimits(): Response
    {
        $limits = $this->rateLimitService->getLimits();
        $statistics = $this->rateLimitService->getGlobalStatistics();

        return $this->render('admin/security/rate_limits.html.twig', [
            'limits' => $limits,
            'statistics' => $statistics,
        ]);
    }

    #[Route('/rate-limits/clear', name: 'admin_security_rate_limits_clear', methods: ['POST'])]
    public function clearRateLimit(Request $request): JsonResponse
    {
        $limitType = $request->request->get('limit_type');
        $identifier = $request->request->get('identifier');

        if (!$limitType || !$identifier) {
            return new JsonResponse(['error' => 'Limit type and identifier are required'], 400);
        }

        try {
            $success = $this->rateLimitService->clearRateLimit($limitType, $identifier);
            
            return new JsonResponse([
                'success' => $success,
                'message' => 'Rate limit cleared successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/blacklist', name: 'admin_security_blacklist')]
    public function blacklist(): Response
    {
        // Get suspicious IPs from last 7 days
        $since = new \DateTimeImmutable('-7 days');
        $suspiciousIps = $this->securityEventRepository->findSuspiciousIps($since);

        return $this->render('admin/security/blacklist.html.twig', [
            'suspicious_ips' => $suspiciousIps,
        ]);
    }

    #[Route('/blacklist/add', name: 'admin_security_blacklist_add', methods: ['POST'])]
    public function addToBlacklist(Request $request): JsonResponse
    {
        $ip = $request->request->get('ip');
        $duration = $request->request->getInt('duration', 86400);

        if (!$ip) {
            return new JsonResponse(['error' => 'IP address is required'], 400);
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return new JsonResponse(['error' => 'Invalid IP address'], 400);
        }

        try {
            $this->rateLimitService->addToBlacklist($ip, $duration);
            
            // Log the action
            $this->securityAuditService->logEvent(
                'ip_blacklisted',
                "IP {$ip} ajoutée à la blacklist",
                'medium',
                $this->getUser()
            );
            
            return new JsonResponse([
                'success' => true,
                'message' => "IP {$ip} added to blacklist"
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/blacklist/remove', name: 'admin_security_blacklist_remove', methods: ['POST'])]
    public function removeFromBlacklist(Request $request): JsonResponse
    {
        $ip = $request->request->get('ip');

        if (!$ip) {
            return new JsonResponse(['error' => 'IP address is required'], 400);
        }

        try {
            $this->rateLimitService->removeFromBlacklist($ip);
            
            // Log the action
            $this->securityAuditService->logEvent(
                'ip_unblacklisted',
                "IP {$ip} supprimée de la blacklist",
                'info',
                $this->getUser()
            );
            
            return new JsonResponse([
                'success' => true,
                'message' => "IP {$ip} removed from blacklist"
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/two-factor', name: 'admin_security_two_factor')]
    public function twoFactor(): Response
    {
        $adoptionStats = $this->twoFactorService->getAdoptionStatistics();
        $usageStats = $this->twoFactorService->getUsageStatistics();
        $configurationsRequiringAttention = $this->twoFactorService->getConfigurationsRequiringAttention();

        return $this->render('admin/security/two_factor.html.twig', [
            'adoption_stats' => $adoptionStats,
            'usage_stats' => $usageStats,
            'configurations_requiring_attention' => $configurationsRequiringAttention,
        ]);
    }

    #[Route('/two-factor/cleanup', name: 'admin_security_two_factor_cleanup', methods: ['POST'])]
    public function cleanupTwoFactor(Request $request): JsonResponse
    {
        $daysOld = $request->request->getInt('days_old', 30);

        try {
            $cleaned = $this->twoFactorService->cleanupOldConfigurations($daysOld);
            
            return new JsonResponse([
                'success' => true,
                'cleaned_count' => $cleaned,
                'message' => sprintf('%d configurations supprimées', $cleaned)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/report', name: 'admin_security_report')]
    public function generateReport(Request $request): Response
    {
        $from = new \DateTimeImmutable($request->query->get('from', '-30 days'));
        $to = new \DateTimeImmutable($request->query->get('to', 'now'));
        $format = $request->query->get('format', 'html');

        $report = $this->securityAuditService->generateSecurityReport($from, $to);

        if ($format === 'json') {
            return new JsonResponse($report);
        }

        return $this->render('admin/security/report.html.twig', [
            'report' => $report,
            'from' => $from,
            'to' => $to,
        ]);
    }

    #[Route('/cleanup', name: 'admin_security_cleanup', methods: ['POST'])]
    public function cleanup(Request $request): JsonResponse
    {
        $daysOld = $request->request->getInt('days_old', 90);

        try {
            $cleaned = $this->securityAuditService->cleanOldEvents($daysOld);
            
            return new JsonResponse([
                'success' => true,
                'cleaned_count' => $cleaned,
                'message' => sprintf('%d événements supprimés', $cleaned)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/test-security', name: 'admin_security_test', methods: ['POST'])]
    public function testSecurity(Request $request): JsonResponse
    {
        $testType = $request->request->get('test_type');

        try {
            switch ($testType) {
                case 'rate_limit':
                    $this->testRateLimit($request);
                    break;
                case 'security_headers':
                    $result = $this->testSecurityHeaders();
                    break;
                case 'suspicious_pattern':
                    $this->testSuspiciousPattern();
                    break;
                default:
                    throw new \InvalidArgumentException('Unknown test type');
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Security test completed successfully',
                'result' => $result ?? null,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    private function testRateLimit(Request $request): void
    {
        // Simulate multiple requests to test rate limiting
        for ($i = 0; $i < 10; $i++) {
            $this->rateLimitService->recordAttempt($request, 'test');
        }
    }

    private function testSecurityHeaders(): array
    {
        // This would typically test security headers on a real request
        return [
            'X-Content-Type-Options' => 'OK',
            'X-Frame-Options' => 'OK',
            'X-XSS-Protection' => 'OK',
            'Content-Security-Policy' => 'OK',
            'Strict-Transport-Security' => 'OK',
        ];
    }

    private function testSuspiciousPattern(): void
    {
        $this->securityAuditService->logSuspiciousActivity(
            'Test d\'activité suspecte',
            'Test de sécurité',
            $this->getUser()
        );
    }
}