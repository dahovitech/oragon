<?php

namespace App\Controller\Admin;

use App\Entity\Brand;
use App\Repository\BrandRepository;
use App\Repository\LanguageRepository;
use App\Service\EcommerceTranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/brand', name: 'admin_brand_')]
#[IsGranted('ROLE_ADMIN')]
class BrandController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BrandRepository $brandRepository,
        private LanguageRepository $languageRepository,
        private EcommerceTranslationService $translationService
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search');
        $status = $request->query->get('status');
        
        $queryBuilder = $this->brandRepository->createQueryBuilder('b');
        
        if ($search) {
            $queryBuilder
                ->leftJoin('b.translations', 't')
                ->andWhere('t.name LIKE :search OR b.website LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        if ($status !== null) {
            $queryBuilder
                ->andWhere('b.isActive = :status')
                ->setParameter('status', (bool) $status);
        }
        
        $queryBuilder->orderBy('b.sortOrder', 'ASC')->addOrderBy('b.id', 'DESC');
        
        $brands = $queryBuilder->getQuery()->getResult();
        $languages = $this->languageRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);

        return $this->render('admin/brand/index.html.twig', [
            'brands' => $brands,
            'languages' => $languages,
            'filters' => [
                'search' => $search,
                'status' => $status
            ]
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $brand = new Brand();
        $languages = $this->languageRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);

        if ($request->isMethod('POST')) {
            return $this->handleBrandSubmission($request, $brand, $languages);
        }

        return $this->render('admin/brand/new.html.twig', [
            'brand' => $brand,
            'languages' => $languages
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Brand $brand): Response
    {
        $languages = $this->languageRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);

        if ($request->isMethod('POST')) {
            return $this->handleBrandSubmission($request, $brand, $languages);
        }

        return $this->render('admin/brand/edit.html.twig', [
            'brand' => $brand,
            'languages' => $languages
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Brand $brand): Response
    {
        $languages = $this->languageRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);
        $products = $brand->getProducts();

        return $this->render('admin/brand/show.html.twig', [
            'brand' => $brand,
            'languages' => $languages,
            'products' => $products
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST', 'DELETE'])]
    public function delete(Request $request, Brand $brand): Response
    {
        if ($this->isCsrfTokenValid('delete' . $brand->getId(), $request->request->get('_token'))) {
            // Check if brand has products
            if (!$brand->getProducts()->isEmpty()) {
                $this->addFlash('error', 'Cannot delete brand with products');
                return $this->redirectToRoute('admin_brand_index');
            }

            $this->entityManager->remove($brand);
            $this->entityManager->flush();

            $this->addFlash('success', 'Brand deleted successfully');
        } else {
            $this->addFlash('error', 'Invalid token');
        }

        return $this->redirectToRoute('admin_brand_index');
    }

    #[Route('/reorder', name: 'reorder', methods: ['POST'])]
    public function reorder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['brands'])) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid data']);
        }

        foreach ($data['brands'] as $item) {
            $brand = $this->brandRepository->find($item['id']);
            if ($brand) {
                $brand->setSortOrder($item['position']);
            }
        }

        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id}/duplicate-translation', name: 'duplicate_translation', methods: ['POST'])]
    public function duplicateTranslation(Request $request, Brand $brand): JsonResponse
    {
        $sourceLanguage = $request->request->get('source_language');
        $targetLanguage = $request->request->get('target_language');

        if (!$sourceLanguage || !$targetLanguage) {
            return new JsonResponse(['success' => false, 'message' => 'Missing parameters']);
        }

        $success = $this->translationService->duplicateTranslation(
            Brand::class,
            $brand->getId(),
            $sourceLanguage,
            $targetLanguage
        );

        if ($success) {
            return new JsonResponse(['success' => true, 'message' => 'Translation duplicated successfully']);
        } else {
            return new JsonResponse(['success' => false, 'message' => 'Failed to duplicate translation']);
        }
    }

    #[Route('/bulk-action', name: 'bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request): Response
    {
        $action = $request->request->get('action');
        $brandIds = $request->request->get('brands', []);

        if (empty($brandIds)) {
            $this->addFlash('warning', 'No brands selected');
            return $this->redirectToRoute('admin_brand_index');
        }

        $brands = $this->brandRepository->findBy(['id' => $brandIds]);
        $count = 0;

        switch ($action) {
            case 'activate':
                foreach ($brands as $brand) {
                    $brand->setIsActive(true);
                    $count++;
                }
                $this->addFlash('success', sprintf('%d brands activated', $count));
                break;

            case 'deactivate':
                foreach ($brands as $brand) {
                    $brand->setIsActive(false);
                    $count++;
                }
                $this->addFlash('success', sprintf('%d brands deactivated', $count));
                break;

            case 'delete':
                foreach ($brands as $brand) {
                    // Check constraints before deletion
                    if ($brand->getProducts()->isEmpty()) {
                        $this->entityManager->remove($brand);
                        $count++;
                    }
                }
                $this->addFlash('success', sprintf('%d brands deleted', $count));
                break;

            default:
                $this->addFlash('error', 'Unknown action');
                return $this->redirectToRoute('admin_brand_index');
        }

        $this->entityManager->flush();
        return $this->redirectToRoute('admin_brand_index');
    }

    private function handleBrandSubmission(Request $request, Brand $brand, array $languages): Response
    {
        $data = $request->request->all();

        // Validate required fields
        $errors = $this->validateBrandData($data);
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('admin_brand_edit', ['id' => $brand->getId()]);
        }

        // Set basic brand data
        $brand->setWebsite($data['website'] ?? null);
        $brand->setLogo($data['logo'] ?? null);
        $brand->setIsActive(isset($data['is_active']));
        $brand->setSortOrder((int) ($data['sort_order'] ?? 0));

        // Handle translations
        $translationsData = [];
        foreach ($languages as $language) {
            $langCode = $language->getCode();
            if (isset($data['translations'][$langCode])) {
                $translationsData[$langCode] = $data['translations'][$langCode];
            }
        }

        // Save brand with translations
        $this->translationService->createOrUpdateBrand($brand, $translationsData);

        $this->entityManager->persist($brand);
        $this->entityManager->flush();

        $this->addFlash('success', 'Brand saved successfully');

        return $this->redirectToRoute('admin_brand_edit', ['id' => $brand->getId()]);
    }

    private function validateBrandData(array $data): array
    {
        $errors = [];

        // Validate website URL if provided
        if (!empty($data['website'])) {
            if (!filter_var($data['website'], FILTER_VALIDATE_URL)) {
                $errors[] = 'Website must be a valid URL';
            }
        }

        // Validate at least one translation
        $hasTranslation = false;
        if (isset($data['translations'])) {
            foreach ($data['translations'] as $translation) {
                if (!empty($translation['name'])) {
                    $hasTranslation = true;
                    break;
                }
            }
        }

        if (!$hasTranslation) {
            $errors[] = 'At least one translation name is required';
        }

        // Validate sort order
        if (isset($data['sort_order']) && !is_numeric($data['sort_order'])) {
            $errors[] = 'Sort order must be a number';
        }

        return $errors;
    }
}