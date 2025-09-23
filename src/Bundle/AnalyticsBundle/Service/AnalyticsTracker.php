<?php

namespace App\Bundle\AnalyticsBundle\Service;

use App\Bundle\AnalyticsBundle\Entity\PageView;
use App\Bundle\AnalyticsBundle\Entity\Event;
use App\Bundle\AnalyticsBundle\Repository\PageViewRepository;
use App\Bundle\AnalyticsBundle\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AnalyticsTracker
{
    private EntityManagerInterface $entityManager;
    private PageViewRepository $pageViewRepository;
    private EventRepository $eventRepository;
    private TokenStorageInterface $tokenStorage;

    public function __construct(
        EntityManagerInterface $entityManager,
        PageViewRepository $pageViewRepository,
        EventRepository $eventRepository,
        TokenStorageInterface $tokenStorage
    ) {
        $this->entityManager = $entityManager;
        $this->pageViewRepository = $pageViewRepository;
        $this->eventRepository = $eventRepository;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * Track page view
     */
    public function trackPageView(Request $request, ?string $title = null): PageView
    {
        $pageView = new PageView();
        $pageView->setUrl($request->getRequestUri());
        $pageView->setTitle($title ?? $request->get('_route', 'Unknown Page'));
        $pageView->setReferrer($request->headers->get('referer'));
        $pageView->setIpAddress($this->getClientIpAddress($request));
        $pageView->setUserAgent($request->headers->get('user-agent'));
        $pageView->setSessionId($request->getSession()->getId());

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if ($user && method_exists($user, 'getId')) {
            $pageView->setUserId($user->getId());
        }

        // Ajouter métadonnées additionnelles
        $metadata = [
            'method' => $request->getMethod(),
            'is_ajax' => $request->isXmlHttpRequest(),
            'is_secure' => $request->isSecure(),
            'locale' => $request->getLocale(),
        ];
        $pageView->setMetadata($metadata);

        $this->pageViewRepository->save($pageView, true);

        return $pageView;
    }

    /**
     * Track custom event
     */
    public function trackEvent(
        string $eventType,
        string $category,
        ?string $action = null,
        ?string $label = null,
        ?int $value = null,
        ?string $url = null,
        ?array $properties = null,
        ?Request $request = null
    ): Event {
        $event = new Event();
        $event->setEventType($eventType);
        $event->setCategory($category);
        $event->setAction($action);
        $event->setLabel($label);
        $event->setValue($value);
        $event->setUrl($url);
        $event->setProperties($properties);

        if ($request) {
            $event->setIpAddress($this->getClientIpAddress($request));
            $event->setSessionId($request->getSession()->getId());
        } else {
            $event->setIpAddress('127.0.0.1');
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if ($user && method_exists($user, 'getId')) {
            $event->setUserId($user->getId());
        }

        $this->eventRepository->save($event, true);

        return $event;
    }

    /**
     * Track specific events for e-commerce
     */
    public function trackEcommerceEvent(string $action, array $data, ?Request $request = null): Event
    {
        return $this->trackEvent(
            'ecommerce',
            'commerce',
            $action,
            $data['label'] ?? null,
            $data['value'] ?? null,
            $data['url'] ?? null,
            $data,
            $request
        );
    }

    /**
     * Track blog-related events
     */
    public function trackBlogEvent(string $action, array $data, ?Request $request = null): Event
    {
        return $this->trackEvent(
            'blog',
            'content',
            $action,
            $data['label'] ?? null,
            $data['value'] ?? null,
            $data['url'] ?? null,
            $data,
            $request
        );
    }

    /**
     * Track user-related events
     */
    public function trackUserEvent(string $action, array $data, ?Request $request = null): Event
    {
        return $this->trackEvent(
            'user',
            'authentication',
            $action,
            $data['label'] ?? null,
            $data['value'] ?? null,
            $data['url'] ?? null,
            $data,
            $request
        );
    }

    /**
     * Track search events
     */
    public function trackSearchEvent(string $query, int $results, ?Request $request = null): Event
    {
        return $this->trackEvent(
            'search',
            'site_search',
            'search',
            $query,
            $results,
            $request ? $request->getRequestUri() : null,
            ['query' => $query, 'results_count' => $results],
            $request
        );
    }

    /**
     * Track download events
     */
    public function trackDownloadEvent(string $filename, string $url, ?Request $request = null): Event
    {
        return $this->trackEvent(
            'download',
            'file',
            'download',
            $filename,
            null,
            $url,
            ['filename' => $filename, 'file_url' => $url],
            $request
        );
    }

    /**
     * Track form submission events
     */
    public function trackFormEvent(string $formName, bool $success, ?array $errors = null, ?Request $request = null): Event
    {
        return $this->trackEvent(
            'form',
            'interaction',
            $success ? 'submit_success' : 'submit_error',
            $formName,
            $success ? 1 : 0,
            $request ? $request->getRequestUri() : null,
            ['form_name' => $formName, 'success' => $success, 'errors' => $errors],
            $request
        );
    }

    /**
     * Track API usage
     */
    public function trackApiEvent(string $endpoint, string $method, int $statusCode, ?Request $request = null): Event
    {
        return $this->trackEvent(
            'api',
            'usage',
            $method,
            $endpoint,
            $statusCode,
            $endpoint,
            ['endpoint' => $endpoint, 'method' => $method, 'status_code' => $statusCode],
            $request
        );
    }

    /**
     * Get client IP address
     */
    private function getClientIpAddress(Request $request): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            $ip = $request->server->get($key);
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
        
        return $request->getClientIp() ?? '127.0.0.1';
    }

    /**
     * Batch track multiple events
     */
    public function batchTrackEvents(array $events): void
    {
        $this->entityManager->beginTransaction();
        
        try {
            foreach ($events as $eventData) {
                $this->trackEvent(
                    $eventData['eventType'],
                    $eventData['category'],
                    $eventData['action'] ?? null,
                    $eventData['label'] ?? null,
                    $eventData['value'] ?? null,
                    $eventData['url'] ?? null,
                    $eventData['properties'] ?? null
                );
            }
            
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }
}