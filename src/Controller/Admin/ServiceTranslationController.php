<?php

namespace App\Controller\Admin;

use App\Entity\Service;
use App\Entity\ServiceTranslation;
use App\Entity\Language;
use App\Form\ServiceTranslationType;
use App\Repository\ServiceTranslationRepository;
use App\Repository\LanguageRepository;
use App\Service\ServiceTranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/service-translations', name: 'admin_service_translation_')]
class ServiceTranslationController extends AbstractController
{
    public function __construct(
        private ServiceTranslationService $serviceTranslationService
    ) {}

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(ServiceTranslationRepository $repository, LanguageRepository $languageRepository): Response
    {
        $languages = $languageRepository->findActiveLanguages();
        $statistics = $repository->getDetailedStatistics();

        return $this->render('admin/service_translation/index.html.twig', [
            'languages' => $languages,
            'statistics' => $statistics,
        ]);
    }

    #[Route('/by-language/{code}', name: 'by_language', methods: ['GET'])]
    public function byLanguage(string $code, LanguageRepository $languageRepository, ServiceTranslationRepository $repository): Response
    {
        $language = $languageRepository->findActiveByCode($code);
        
        if (!$language) {
            throw $this->createNotFoundException('Langue non trouvée');
        }

        $translations = $repository->findByLanguage($language);
        $statistics = $repository->countByStatus($language);

        return $this->render('admin/service_translation/by_language.html.twig', [
            'language' => $language,
            'translations' => $translations,
            'statistics' => $statistics,
        ]);
    }

    #[Route('/service/{id}/new/{languageCode}', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, Service $service, string $languageCode, LanguageRepository $languageRepository): Response
    {
        $language = $languageRepository->findActiveByCode($languageCode);
        
        if (!$language) {
            throw $this->createNotFoundException('Langue non trouvée');
        }

        // Vérifier qu'une traduction n'existe pas déjà
        $existingTranslation = $service->getTranslation($languageCode);
        if ($existingTranslation) {
            $this->addFlash('warning', 'Une traduction existe déjà pour cette langue.');
            return $this->redirectToRoute('admin_service_translation_edit', ['id' => $existingTranslation->getId()]);
        }

        $translation = new ServiceTranslation();
        $translation->setTranslatable($service)
                   ->setLanguage($language);

        $form = $this->createForm(ServiceTranslationType::class, $translation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->serviceTranslationService->saveServiceWithTranslations($service, [$languageCode => $translation]);
                
                $this->addFlash('success', sprintf('Traduction créée avec succès pour %s.', $language->getName()));
                
                return $this->redirectToRoute('admin_service_edit_translations', ['id' => $service->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création : ' . $e->getMessage());
            }
        }

        return $this->render('admin/service_translation/new.html.twig', [
            'service' => $service,
            'language' => $language,
            'translation' => $translation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ServiceTranslation $translation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ServiceTranslationType::class, $translation, [
            'is_edit_mode' => true
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Traduction mise à jour avec succès.');

            return $this->redirectToRoute('admin_service_edit_translations', [
                'id' => $translation->getTranslatable()->getId()
            ]);
        }

        return $this->render('admin/service_translation/edit.html.twig', [
            'translation' => $translation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(ServiceTranslation $translation): Response
    {
        return $this->render('admin/service_translation/show.html.twig', [
            'translation' => $translation,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, ServiceTranslation $translation): Response
    {
        $serviceId = $translation->getTranslatable()->getId();
        
        if ($this->isCsrfTokenValid('delete'.$translation->getId(), $request->getPayload()->get('_token'))) {
            $this->serviceTranslationService->deleteTranslation($translation);

            $this->addFlash('success', 'Traduction supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_service_edit_translations', ['id' => $serviceId]);
    }

    #[Route('/missing/{languageCode}', name: 'missing', methods: ['GET'])]
    public function missing(string $languageCode, LanguageRepository $languageRepository): Response
    {
        $language = $languageRepository->findActiveByCode($languageCode);
        
        if (!$language) {
            throw $this->createNotFoundException('Langue non trouvée');
        }

        $servicesNeedingTranslation = $this->serviceTranslationService->getServicesNeedingTranslation($languageCode);

        return $this->render('admin/service_translation/missing.html.twig', [
            'language' => $language,
            'services' => $servicesNeedingTranslation,
        ]);
    }

    #[Route('/duplicate/{id}/{targetLanguageCode}', name: 'duplicate', methods: ['POST'])]
    public function duplicate(ServiceTranslation $sourceTranslation, string $targetLanguageCode): Response
    {
        try {
            $service = $sourceTranslation->getTranslatable();
            $sourceLanguageCode = $sourceTranslation->getLanguage()->getCode();
            
            $newTranslation = $this->serviceTranslationService->duplicateTranslation(
                $service, 
                $sourceLanguageCode, 
                $targetLanguageCode
            );

            $this->addFlash('success', 'Traduction dupliquée avec succès.');
            
            return $this->redirectToRoute('admin_service_translation_edit', ['id' => $newTranslation->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la duplication : ' . $e->getMessage());
            
            return $this->redirectToRoute('admin_service_translation_show', ['id' => $sourceTranslation->getId()]);
        }
    }

    #[Route('/status/{status}', name: 'by_status', methods: ['GET'])]
    public function byStatus(string $status, ServiceTranslationRepository $repository): Response
    {
        $validStatuses = ['complete', 'partial', 'started', 'empty'];
        
        if (!in_array($status, $validStatuses)) {
            throw $this->createNotFoundException('Statut invalide');
        }

        $translations = $repository->createQueryBuilder('st')
            ->leftJoin('st.translatable', 's')
            ->leftJoin('st.language', 'l')
            ->addSelect('s', 'l')
            ->orderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();

        // Filtrer par statut
        $filteredTranslations = array_filter($translations, function(ServiceTranslation $translation) use ($status) {
            return $translation->getTranslationStatus() === $status;
        });

        return $this->render('admin/service_translation/by_status.html.twig', [
            'status' => $status,
            'translations' => $filteredTranslations,
        ]);
    }
}
