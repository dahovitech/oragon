<?php

namespace App\Bundle\AnalyticsBundle\Controller;

use App\Bundle\AnalyticsBundle\Service\ReportGenerator;
use App\Bundle\AnalyticsBundle\Service\AnalyticsTracker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/analytics', name: 'admin_analytics_')]
#[IsGranted('ROLE_ADMIN')]
class AnalyticsController extends AbstractController
{
    private ReportGenerator $reportGenerator;
    private AnalyticsTracker $analyticsTracker;

    public function __construct(
        ReportGenerator $reportGenerator,
        AnalyticsTracker $analyticsTracker
    ) {
        $this->reportGenerator = $reportGenerator;
        $this->analyticsTracker = $analyticsTracker;
    }

    #[Route('', name: 'dashboard')]
    public function dashboard(): Response
    {
        return $this->render('analytics/dashboard.html.twig');
    }

    #[Route('/api/dashboard', name: 'api_dashboard', methods: ['GET'])]
    public function getDashboardData(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to = $request->query->get('to');

        $fromDate = $from ? new \DateTimeImmutable($from) : new \DateTimeImmutable('-30 days');
        $toDate = $to ? new \DateTimeImmutable($to) : new \DateTimeImmutable();

        $data = $this->reportGenerator->generateDashboard($fromDate, $toDate);

        return new JsonResponse($data);
    }

    #[Route('/api/overview', name: 'api_overview', methods: ['GET'])]
    public function getOverview(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to = $request->query->get('to');

        $fromDate = $from ? new \DateTimeImmutable($from) : new \DateTimeImmutable('-30 days');
        $toDate = $to ? new \DateTimeImmutable($to) : new \DateTimeImmutable();

        $data = $this->reportGenerator->getOverviewStats($fromDate, $toDate);

        return new JsonResponse($data);
    }

    #[Route('/api/traffic', name: 'api_traffic', methods: ['GET'])]
    public function getTrafficReport(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to = $request->query->get('to');

        $fromDate = $from ? new \DateTimeImmutable($from) : new \DateTimeImmutable('-30 days');
        $toDate = $to ? new \DateTimeImmutable($to) : new \DateTimeImmutable();

        $data = $this->reportGenerator->getTrafficReport($fromDate, $toDate);

        return new JsonResponse($data);
    }

    #[Route('/api/content', name: 'api_content', methods: ['GET'])]
    public function getContentReport(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to = $request->query->get('to');

        $fromDate = $from ? new \DateTimeImmutable($from) : new \DateTimeImmutable('-30 days');
        $toDate = $to ? new \DateTimeImmutable($to) : new \DateTimeImmutable();

        $data = $this->reportGenerator->getContentReport($fromDate, $toDate);

        return new JsonResponse($data);
    }

    #[Route('/api/ecommerce', name: 'api_ecommerce', methods: ['GET'])]
    public function getEcommerceReport(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to = $request->query->get('to');

        $fromDate = $from ? new \DateTimeImmutable($from) : new \DateTimeImmutable('-30 days');
        $toDate = $to ? new \DateTimeImmutable($to) : new \DateTimeImmutable();

        $data = $this->reportGenerator->getEcommerceReport($fromDate, $toDate);

        return new JsonResponse($data);
    }

    #[Route('/api/users', name: 'api_users', methods: ['GET'])]
    public function getUserReport(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to = $request->query->get('to');

        $fromDate = $from ? new \DateTimeImmutable($from) : new \DateTimeImmutable('-30 days');
        $toDate = $to ? new \DateTimeImmutable($to) : new \DateTimeImmutable();

        $data = $this->reportGenerator->getUserReport($fromDate, $toDate);

        return new JsonResponse($data);
    }

    #[Route('/api/realtime', name: 'api_realtime', methods: ['GET'])]
    public function getRealTimeData(): JsonResponse
    {
        $data = $this->reportGenerator->getRealTimeData();

        return new JsonResponse($data);
    }

    #[Route('/traffic', name: 'traffic')]
    public function traffic(): Response
    {
        return $this->render('analytics/traffic.html.twig');
    }

    #[Route('/content', name: 'content')]
    public function content(): Response
    {
        return $this->render('analytics/content.html.twig');
    }

    #[Route('/ecommerce', name: 'ecommerce')]
    public function ecommerce(): Response
    {
        return $this->render('analytics/ecommerce.html.twig');
    }

    #[Route('/users', name: 'users')]
    public function users(): Response
    {
        return $this->render('analytics/users.html.twig');
    }

    #[Route('/realtime', name: 'realtime')]
    public function realtime(): Response
    {
        return $this->render('analytics/realtime.html.twig');
    }

    #[Route('/export/{type}', name: 'export', methods: ['GET'])]
    public function exportReport(string $type, Request $request): Response
    {
        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $format = $request->query->get('format', 'csv');

        $fromDate = $from ? new \DateTimeImmutable($from) : new \DateTimeImmutable('-30 days');
        $toDate = $to ? new \DateTimeImmutable($to) : new \DateTimeImmutable();

        switch ($type) {
            case 'traffic':
                $data = $this->reportGenerator->getTrafficReport($fromDate, $toDate);
                break;
            case 'content':
                $data = $this->reportGenerator->getContentReport($fromDate, $toDate);
                break;
            case 'ecommerce':
                $data = $this->reportGenerator->getEcommerceReport($fromDate, $toDate);
                break;
            case 'users':
                $data = $this->reportGenerator->getUserReport($fromDate, $toDate);
                break;
            default:
                $data = $this->reportGenerator->generateDashboard($fromDate, $toDate);
        }

        if ($format === 'json') {
            $response = new JsonResponse($data);
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $type . '_report.json"');
            return $response;
        }

        // Export CSV
        $csv = $this->generateCsv($data, $type);
        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $type . '_report.csv"');

        return $response;
    }

    #[Route('/api/track', name: 'api_track', methods: ['POST'])]
    public function trackEvent(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['eventType']) || !isset($data['category'])) {
            return new JsonResponse(['error' => 'Invalid event data'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $event = $this->analyticsTracker->trackEvent(
                $data['eventType'],
                $data['category'],
                $data['action'] ?? null,
                $data['label'] ?? null,
                $data['value'] ?? null,
                $data['url'] ?? null,
                $data['properties'] ?? null,
                $request
            );

            return new JsonResponse(['success' => true, 'eventId' => $event->getId()]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to track event'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate CSV from array data
     */
    private function generateCsv(array $data, string $type): string
    {
        $output = fopen('php://temp', 'r+');

        switch ($type) {
            case 'traffic':
                fputcsv($output, ['Date', 'Page Views', 'Unique Visitors']);
                if (isset($data['daily_views'])) {
                    foreach ($data['daily_views'] as $row) {
                        fputcsv($output, [$row['date'], $row['views'], '']);
                    }
                }
                break;

            case 'content':
                fputcsv($output, ['URL', 'Title', 'Views']);
                if (isset($data['top_posts'])) {
                    foreach ($data['top_posts'] as $row) {
                        fputcsv($output, [$row['url'], $row['title'], $row['views']]);
                    }
                }
                break;

            case 'ecommerce':
                fputcsv($output, ['Metric', 'Value']);
                if (isset($data['order_statistics'])) {
                    foreach ($data['order_statistics'] as $key => $value) {
                        fputcsv($output, [ucfirst(str_replace('_', ' ', $key)), $value]);
                    }
                }
                break;

            case 'users':
                fputcsv($output, ['Date', 'New Users']);
                if (isset($data['new_users'])) {
                    foreach ($data['new_users'] as $row) {
                        fputcsv($output, [$row['date'], $row['count']]);
                    }
                }
                break;

            default:
                fputcsv($output, ['Metric', 'Value']);
                if (isset($data['overview'])) {
                    foreach ($data['overview'] as $key => $value) {
                        fputcsv($output, [ucfirst(str_replace('_', ' ', $key)), $value]);
                    }
                }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}