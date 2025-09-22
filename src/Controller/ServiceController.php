<?php

namespace App\Controller;

use App\Entity\Service;
use App\Repository\ServiceRepository;
use App\Service\LocalizationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur frontend pour l'affichage multilingue des services
 */
class ServiceController extends AbstractController
{
    public function __construct(
        private LocalizationService $localizationService
    ) {}

    /**
     * Liste de tous les services dans la langue courante
     */
    #[Route('/{_locale}/services', name: 'service_index', requirements: ['_locale' => '[a-z]{2}'], methods: ['GET'])]
    public function index(ServiceRepository $serviceRepository): Response
    {
        $locale = $this->localizationService->getCurrentLocale();
        $services = $serviceRepository->findWithTranslations($locale);
        
        // Définir la langue courante pour chaque service
        foreach ($services as $service) {
            $service->setCurrentLocale($locale);
        }

        return $this->render('service/index.html.twig', [
            'services' => $services,
            'current_language' => $this->localizationService->getCurrentLanguage(),
        ]);
    }

    /**
     * Affichage d'un service spécifique
     */
    #[Route('/{_locale}/services/{slug}', name: 'service_show', requirements: ['_locale' => '[a-z]{2}'], methods: ['GET'])]
    public function show(string $slug, ServiceRepository $serviceRepository): Response
    {
        $locale = $this->localizationService->getCurrentLocale();
        $service = $serviceRepository->findBySlugWithTranslations($slug);

        if (!$service || !$service->isActive()) {
            throw $this->createNotFoundException('Service non trouvé');
        }

        // Vérifier si le service est traduit dans la langue courante
        $translation = $service->getTranslation($locale);
        
        if (!$translation || !$translation->isComplete()) {
            // Tentative de fallback vers la langue par défaut
            $defaultLocale = $this->localizationService->getDefaultLocale();
            $defaultTranslation = $service->getTranslation($defaultLocale);
            
            if (!$defaultTranslation || !$defaultTranslation->isComplete()) {
                // Si pas de traduction par défaut, utiliser la première traduction disponible
                $availableTranslations = $service->getTranslations()->filter(fn($t) => $t->isComplete());
                if ($availableTranslations->isEmpty()) {
                    throw $this->createNotFoundException('Service non disponible dans cette langue');
                }
                
                $firstTranslation = $availableTranslations->first();
                $locale = $firstTranslation->getLanguage()->getCode();
                
                // Rediriger vers la langue où le contenu est disponible
                return $this->redirectToRoute('service_show', [
                    '_locale' => $locale,
                    'slug' => $slug
                ]);
            } else {
                // Rediriger vers la langue par défaut
                return $this->redirectToRoute('service_show', [
                    '_locale' => $defaultLocale,
                    'slug' => $slug
                ]);
            }
        }

        // Définir la langue courante pour le service
        $service->setCurrentLocale($locale);

        return $this->render('service/show.html.twig', [
            'service' => $service,
            'translation' => $translation,
            'current_language' => $this->localizationService->getCurrentLanguage(),
            'is_fallback' => false,
        ]);
    }

    /**
     * Page d'accueil des services (redirection vers la liste)
     */
    #[Route('/{_locale}/', name: 'homepage', requirements: ['_locale' => '[a-z]{2}'], methods: ['GET'])]
    public function homepage(): Response
    {
        return $this->redirectToRoute('service_index', [
            '_locale' => $this->localizationService->getCurrentLocale()
        ]);
    }

    /**
     * API pour obtenir un service en JSON (utile pour AJAX)
     */
    #[Route('/api/{_locale}/services/{slug}', name: 'api_service_show', requirements: ['_locale' => '[a-z]{2}'], methods: ['GET'])]
    public function apiShow(string $slug, ServiceRepository $serviceRepository): Response
    {
        $locale = $this->localizationService->getCurrentLocale();
        $service = $serviceRepository->findBySlugWithTranslations($slug);

        if (!$service || !$service->isActive()) {
            return $this->json(['error' => 'Service not found'], 404);
        }

        $translation = $service->getTranslation($locale);
        
        if (!$translation) {
            // Fallback vers la langue par défaut
            $defaultLocale = $this->localizationService->getDefaultLocale();
            $translation = $service->getTranslation($defaultLocale);
            
            if (!$translation) {
                return $this->json(['error' => 'No translation available'], 404);
            }
        }

        $data = [
            'id' => $service->getId(),
            'slug' => $service->getSlug(),
            'title' => $translation->getTitle(),
            'description' => $translation->getDescription(),
            'content' => $translation->getContent(),
            'meta_title' => $translation->getMetaTitle(),
            'meta_description' => $translation->getMetaDescription(),
            'is_active' => $service->isActive(),
            'sort_order' => $service->getSortOrder(),
            'image' => $service->getImage() ? [
                'filename' => $service->getImage()->getFileName(),
                'alt' => $service->getImage()->getAlt(),
                'url' => $this->generateUrl('homepage') . 'upload/media/' . $service->getImage()->getFileName()
            ] : null,
            'language' => [
                'code' => $translation->getLanguage()->getCode(),
                'name' => $translation->getLanguage()->getName(),
                'native_name' => $translation->getLanguage()->getNativeName()
            ],
            'available_languages' => array_map(function($availableLocale) use ($service) {
                $lang = $this->localizationService->getActiveLanguages();
                $langEntity = array_filter($lang, fn($l) => $l->getCode() === $availableLocale);
                $langEntity = reset($langEntity);
                
                return [
                    'code' => $availableLocale,
                    'name' => $langEntity ? $langEntity->getName() : $availableLocale,
                    'has_translation' => $service->hasTranslation($availableLocale),
                    'is_complete' => $service->isTranslatedInto($availableLocale)
                ];
            }, $service->getAvailableLocales())
        ];

        return $this->json($data);
    }

    /**
     * Recherche de services
     */
    #[Route('/{_locale}/services/search', name: 'service_search', requirements: ['_locale' => '[a-z]{2}'], methods: ['GET'])]
    public function search(Request $request, ServiceRepository $serviceRepository): Response
    {
        $query = $request->query->get('q', '');
        $locale = $this->localizationService->getCurrentLocale();
        
        if (empty(trim($query))) {
            return $this->redirectToRoute('service_index', ['_locale' => $locale]);
        }

        // Recherche simple dans les titres et descriptions
        $services = $serviceRepository->createQueryBuilder('s')
            ->leftJoin('s.translations', 't')
            ->leftJoin('t.language', 'l')
            ->where('s.isActive = :active')
            ->andWhere('l.code = :locale')
            ->andWhere('(t.title LIKE :query OR t.description LIKE :query OR t.content LIKE :query)')
            ->setParameter('active', true)
            ->setParameter('locale', $locale)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();

        // Définir la langue courante pour chaque service
        foreach ($services as $service) {
            $service->setCurrentLocale($locale);
        }

        return $this->render('service/search.html.twig', [
            'services' => $services,
            'query' => $query,
            'current_language' => $this->localizationService->getCurrentLanguage(),
        ]);
    }

    /**
     * Changement de langue (via AJAX ou redirection)
     */
    #[Route('/change-language/{locale}', name: 'change_language', requirements: ['locale' => '[a-z]{2}'], methods: ['POST', 'GET'])]
    public function changeLanguage(string $locale, Request $request): Response
    {
        if (!$this->localizationService->isLanguageAvailable($locale)) {
            throw $this->createNotFoundException('Langue non disponible');
        }

        // Sauvegarder la préférence dans la session et un cookie
        $session = $request->getSession();
        $session->set('_locale', $locale);
        
        // Obtenir l'URL de destination
        $targetUrl = $this->localizationService->getCurrentUrlInLocale($locale);
        
        $response = new Response();
        
        // Si c'est une requête AJAX, retourner du JSON
        if ($request->isXmlHttpRequest()) {
            $response = $this->json([
                'success' => true,
                'locale' => $locale,
                'redirect_url' => $targetUrl
            ]);
        } else {
            // Sinon, rediriger directement
            $response = $this->redirect($targetUrl);
        }
        
        // Définir le cookie de langue (valide 1 an)
        $response->headers->setCookie(
            new \Symfony\Component\HttpFoundation\Cookie(
                'locale',
                $locale,
                time() + (365 * 24 * 60 * 60), // 1 an
                '/',
                null,
                false,
                true // HttpOnly
            )
        );

        return $response;
    }
}
