<?php

namespace App\Controller\Admin;

use App\Entity\Service;
use App\Entity\ServiceTranslation;
use App\Form\ServiceType;
use App\Form\ServiceWithTranslationsType;
use App\Repository\ServiceRepository;
use App\Repository\LanguageRepository;
use App\Service\ServiceTranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/services', name: 'admin_service_')]
class ServiceController extends AbstractController
{
    public function __construct(
        private ServiceTranslationService $serviceTranslationService,
        private SluggerInterface $slugger
    ) {}

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(ServiceRepository $serviceRepository, LanguageRepository $languageRepository): Response
    {
        $services = $serviceRepository->findAllWithTranslations();
        $languages = $languageRepository->findActiveLanguages();
        $statistics = $this->serviceTranslationService->getTranslationStatistics();

        return $this->render('admin/service/index.html.twig', [
            'services' => $services,
            'languages' => $languages,
            'statistics' => $statistics,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ServiceRepository $serviceRepository): Response
    {
        $service = new Service();
        
        // Définir l'ordre de tri par défaut
        $service->setSortOrder($serviceRepository->getNextSortOrder());

        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Générer le slug si nécessaire
            if (empty($service->getSlug())) {
                $slug = $this->slugger->slug($service->getTitle() ?? 'service')->lower();
                $service->setSlug($slug);
            }

            $entityManager->persist($service);
            $entityManager->flush();

            // Initialiser les traductions vides pour toutes les langues
            $this->serviceTranslationService->initializeMissingTranslations($service);

            $this->addFlash('success', 'Service créé avec succès. Vous pouvez maintenant ajouter les traductions.');

            return $this->redirectToRoute('admin_service_edit_translations', ['id' => $service->getId()]);
        }

        return $this->render('admin/service/new.html.twig', [
            'service' => $service,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Service $service): Response
    {
        $translationStats = $service->getTranslationStats();

        return $this->render('admin/service/show.html.twig', [
            'service' => $service,
            'translation_stats' => $translationStats,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Service mis à jour avec succès.');

            return $this->redirectToRoute('admin_service_index');
        }

        return $this->render('admin/service/edit.html.twig', [
            'service' => $service,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit-translations', name: 'edit_translations', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function editTranslations(Request $request, Service $service, LanguageRepository $languageRepository): Response
    {
        $languages = $languageRepository->findActiveLanguages();
        
        // Préparer les données pour le formulaire
        $formData = ['service' => $service];
        
        // Créer le formulaire avec traductions
        $form = $this->createForm(ServiceWithTranslationsType::class, $formData, [
            'service' => $service
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            
            // Extraire les traductions du formulaire
            $translationsData = [];
            foreach ($languages as $language) {
                $fieldName = 'translation_' . $language->getCode();
                if (isset($data[$fieldName])) {
                    $translationsData[$language->getCode()] = $data[$fieldName];
                }
            }

            // Sauvegarder le service avec ses traductions
            $this->serviceTranslationService->saveServiceWithTranslations($service, $translationsData);

            $this->addFlash('success', 'Traductions mises à jour avec succès.');

            return $this->redirectToRoute('admin_service_index');
        }

        return $this->render('admin/service/edit_translations.html.twig', [
            'service' => $service,
            'form' => $form,
            'languages' => $languages,
        ]);
    }

    #[Route('/{id}/duplicate-translation/{sourceLocale}/{targetLocale}', name: 'duplicate_translation', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function duplicateTranslation(Service $service, string $sourceLocale, string $targetLocale): Response
    {
        try {
            $this->serviceTranslationService->duplicateTranslation($service, $sourceLocale, $targetLocale);
            $this->addFlash('success', sprintf('Traduction dupliquée de %s vers %s avec succès.', $sourceLocale, $targetLocale));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la duplication : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_service_edit_translations', ['id' => $service->getId()]);
    }

    #[Route('/{id}/synchronize', name: 'synchronize', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function synchronize(Service $service): Response
    {
        $result = $this->serviceTranslationService->synchronizeServiceWithLanguages($service);

        if ($result['created'] > 0) {
            $this->addFlash('success', sprintf('%d nouvelle(s) traduction(s) créée(s).', $result['created']));
        }

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $locale => $error) {
                $this->addFlash('error', sprintf('Erreur pour %s : %s', $locale, $error));
            }
        }

        if ($result['created'] === 0 && empty($result['errors'])) {
            $this->addFlash('info', 'Aucune traduction manquante trouvée.');
        }

        return $this->redirectToRoute('admin_service_edit_translations', ['id' => $service->getId()]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$service->getId(), $request->getPayload()->get('_token'))) {
            $entityManager->remove($service);
            $entityManager->flush();

            $this->addFlash('success', 'Service supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_service_index');
    }

    #[Route('/statistics', name: 'statistics', methods: ['GET'])]
    public function statistics(): Response
    {
        $statistics = $this->serviceTranslationService->getTranslationStatistics();
        $recentTranslations = $this->serviceTranslationService->getRecentlyUpdatedTranslations(15);

        return $this->render('admin/service/statistics.html.twig', [
            'statistics' => $statistics,
            'recent_translations' => $recentTranslations,
        ]);
    }
}
