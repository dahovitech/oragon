<?php

namespace App\Bundle\AnalyticsBundle\EventListener;

use App\Bundle\AnalyticsBundle\Service\AnalyticsTracker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class AnalyticsListener implements EventSubscriberInterface
{
    private AnalyticsTracker $analyticsTracker;
    private array $ignoredRoutes;
    private array $ignoredPaths;

    public function __construct(AnalyticsTracker $analyticsTracker)
    {
        $this->analyticsTracker = $analyticsTracker;
        
        // Routes à ignorer pour le tracking
        $this->ignoredRoutes = [
            '_profiler',
            '_wdt',
            'admin_analytics_api_track',
            'admin_analytics_api_dashboard',
            'admin_analytics_api_overview',
            'admin_analytics_api_traffic',
            'admin_analytics_api_content',
            'admin_analytics_api_ecommerce',
            'admin_analytics_api_users',
            'admin_analytics_api_realtime'
        ];

        // Chemins à ignorer pour le tracking
        $this->ignoredPaths = [
            '/_profiler',
            '/_wdt',
            '/api/',
            '/admin/analytics/api/',
            '/css/',
            '/js/',
            '/images/',
            '/favicon.ico',
            '/robots.txt',
            '/sitemap.xml'
        ];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
            SecurityEvents::INTERACTIVE_LOGIN => 'onUserLogin'
        ];
    }

    /**
     * Track page views on successful responses
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Ignorer les requêtes non-GET
        if (!$request->isMethod('GET')) {
            return;
        }

        // Ignorer les réponses d'erreur
        if ($response->getStatusCode() >= 400) {
            return;
        }

        // Ignorer les requêtes AJAX sauf indication contraire
        if ($request->isXmlHttpRequest() && !$request->query->get('track_ajax')) {
            return;
        }

        // Ignorer les routes spécifiques
        $route = $request->attributes->get('_route');
        if ($route && in_array($route, $this->ignoredRoutes)) {
            return;
        }

        // Ignorer les chemins spécifiques
        $path = $request->getPathInfo();
        foreach ($this->ignoredPaths as $ignoredPath) {
            if (strpos($path, $ignoredPath) === 0) {
                return;
            }
        }

        try {
            // Déterminer le titre de la page
            $title = $this->getPageTitle($request, $response);
            
            // Tracker la vue de page
            $this->analyticsTracker->trackPageView($request, $title);

            // Tracker des événements spécifiques selon la route
            $this->trackRouteSpecificEvents($request);

        } catch (\Exception $e) {
            // Log l'erreur mais ne pas interrompre la réponse
            // En production, vous pourriez vouloir logger ceci
        }
    }

    /**
     * Track user login events
     */
    public function onUserLogin(InteractiveLoginEvent $event): void
    {
        $request = $event->getRequest();
        $user = $event->getAuthenticationToken()->getUser();

        try {
            $this->analyticsTracker->trackUserEvent('login', [
                'label' => 'user_login',
                'value' => 1,
                'user_id' => method_exists($user, 'getId') ? $user->getId() : null
            ], $request);
        } catch (\Exception $e) {
            // Ignorer les erreurs de tracking
        }
    }

    /**
     * Get page title from various sources
     */
    private function getPageTitle(Request $request, $response): ?string
    {
        $route = $request->attributes->get('_route');
        
        // Titres spécifiques par route
        $routeTitles = [
            'frontend_homepage' => 'Accueil',
            'frontend_contact' => 'Contact',
            'frontend_about' => 'À propos',
            'blog_index' => 'Blog',
            'blog_post_show' => 'Article de blog',
            'blog_category_show' => 'Catégorie du blog',
            'ecommerce_home' => 'Boutique',
            'ecommerce_product_show' => 'Produit',
            'ecommerce_cart' => 'Panier',
            'ecommerce_checkout' => 'Commande',
            'user_profile' => 'Profil utilisateur',
            'user_orders' => 'Mes commandes',
            'admin_dashboard' => 'Tableau de bord admin',
        ];

        if ($route && isset($routeTitles[$route])) {
            return $routeTitles[$route];
        }

        // Essayer d'extraire le titre de la réponse HTML
        if ($response->headers->get('content-type') && 
            strpos($response->headers->get('content-type'), 'text/html') !== false) {
            
            $content = $response->getContent();
            if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $content, $matches)) {
                return trim($matches[1]);
            }
        }

        // Titre par défaut basé sur le chemin
        $path = $request->getPathInfo();
        $segments = array_filter(explode('/', $path));
        
        if (!empty($segments)) {
            return ucfirst(end($segments));
        }

        return 'Page sans titre';
    }

    /**
     * Track specific events based on routes
     */
    private function trackRouteSpecificEvents(Request $request): void
    {
        $route = $request->attributes->get('_route');
        
        switch ($route) {
            case 'blog_post_show':
                // Tracker la lecture d'article
                $this->analyticsTracker->trackBlogEvent('post_view', [
                    'label' => 'blog_post_view',
                    'url' => $request->getRequestUri(),
                    'post_id' => $request->attributes->get('id')
                ], $request);
                break;

            case 'ecommerce_product_show':
                // Tracker la vue de produit
                $this->analyticsTracker->trackEcommerceEvent('product_view', [
                    'label' => 'product_page_view',
                    'url' => $request->getRequestUri(),
                    'product_id' => $request->attributes->get('id')
                ], $request);
                break;

            case 'ecommerce_cart':
                // Tracker la vue du panier
                $this->analyticsTracker->trackEcommerceEvent('cart_view', [
                    'label' => 'cart_page_view',
                    'url' => $request->getRequestUri()
                ], $request);
                break;

            case 'ecommerce_checkout':
                // Tracker le début du checkout
                $this->analyticsTracker->trackEcommerceEvent('checkout_start', [
                    'label' => 'checkout_initiated',
                    'url' => $request->getRequestUri()
                ], $request);
                break;

            case 'blog_category_show':
                // Tracker la vue de catégorie blog
                $this->analyticsTracker->trackBlogEvent('category_view', [
                    'label' => 'blog_category_view',
                    'url' => $request->getRequestUri(),
                    'category_slug' => $request->attributes->get('slug')
                ], $request);
                break;

            case 'search_results':
                // Tracker les recherches
                $query = $request->query->get('q', '');
                if ($query) {
                    $this->analyticsTracker->trackSearchEvent(
                        $query, 
                        0, // Le nombre de résultats devrait être déterminé dans le contrôleur
                        $request
                    );
                }
                break;
        }

        // Tracker les téléchargements
        $path = $request->getPathInfo();
        if (preg_match('/\.(pdf|doc|docx|xls|xlsx|zip|rar)$/i', $path)) {
            $filename = basename($path);
            $this->analyticsTracker->trackDownloadEvent($filename, $request->getRequestUri(), $request);
        }
    }
}