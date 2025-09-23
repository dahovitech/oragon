<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\Language;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
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

#[Route('/admin/product', name: 'admin_product_')]
#[IsGranted('ROLE_ADMIN')]
class ProductController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private BrandRepository $brandRepository,
        private LanguageRepository $languageRepository,
        private EcommerceTranslationService $translationService
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search');
        $category = $request->query->get('category');
        $brand = $request->query->get('brand');
        $status = $request->query->get('status');
        
        $filters = [];
        if ($search) {
            $filters['search'] = $search;
        }
        if ($category) {
            $filters['category'] = $category;
        }
        if ($brand) {
            $filters['brand'] = $brand;
        }
        if ($status !== null) {
            $filters['status'] = (bool) $status;
        }

        $products = $this->productRepository->findWithFilters($filters);
        $categories = $this->categoryRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);
        $brands = $this->brandRepository->findActive();
        $languages = $this->languageRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);

        return $this->render('admin/product/index.html.twig', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'languages' => $languages,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'brand' => $brand,
                'status' => $status
            ]
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $product = new Product();
        $languages = $this->languageRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);
        $categories = $this->categoryRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);
        $brands = $this->brandRepository->findActive();

        if ($request->isMethod('POST')) {
            return $this->handleProductSubmission($request, $product, $languages);
        }

        return $this->render('admin/product/new.html.twig', [
            'product' => $product,
            'languages' => $languages,
            'categories' => $categories,
            'brands' => $brands
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product): Response
    {
        $languages = $this->languageRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);
        $categories = $this->categoryRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);
        $brands = $this->brandRepository->findActive();

        if ($request->isMethod('POST')) {
            return $this->handleProductSubmission($request, $product, $languages);
        }

        return $this->render('admin/product/edit.html.twig', [
            'product' => $product,
            'languages' => $languages,
            'categories' => $categories,
            'brands' => $brands
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        $languages = $this->languageRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);

        return $this->render('admin/product/show.html.twig', [
            'product' => $product,
            'languages' => $languages
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST', 'DELETE'])]
    public function delete(Request $request, Product $product): Response
    {
        if ($this->isCsrfTokenValid('delete' . $product->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($product);
            $this->entityManager->flush();

            $this->addFlash('success', 'Product deleted successfully');
        } else {
            $this->addFlash('error', 'Invalid token');
        }

        return $this->redirectToRoute('admin_product_index');
    }

    #[Route('/bulk-action', name: 'bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request): Response
    {
        $action = $request->request->get('action');
        $productIds = $request->request->get('products', []);

        if (empty($productIds)) {
            $this->addFlash('warning', 'No products selected');
            return $this->redirectToRoute('admin_product_index');
        }

        $products = $this->productRepository->findBy(['id' => $productIds]);
        $count = 0;

        switch ($action) {
            case 'activate':
                foreach ($products as $product) {
                    $product->setIsActive(true);
                    $count++;
                }
                $this->addFlash('success', sprintf('%d products activated', $count));
                break;

            case 'deactivate':
                foreach ($products as $product) {
                    $product->setIsActive(false);
                    $count++;
                }
                $this->addFlash('success', sprintf('%d products deactivated', $count));
                break;

            case 'feature':
                foreach ($products as $product) {
                    $product->setIsFeatured(true);
                    $count++;
                }
                $this->addFlash('success', sprintf('%d products marked as featured', $count));
                break;

            case 'unfeature':
                foreach ($products as $product) {
                    $product->setIsFeatured(false);
                    $count++;
                }
                $this->addFlash('success', sprintf('%d products removed from featured', $count));
                break;

            case 'delete':
                foreach ($products as $product) {
                    $this->entityManager->remove($product);
                    $count++;
                }
                $this->addFlash('success', sprintf('%d products deleted', $count));
                break;

            default:
                $this->addFlash('error', 'Unknown action');
                return $this->redirectToRoute('admin_product_index');
        }

        $this->entityManager->flush();
        return $this->redirectToRoute('admin_product_index');
    }

    #[Route('/{id}/duplicate-translation', name: 'duplicate_translation', methods: ['POST'])]
    public function duplicateTranslation(Request $request, Product $product): JsonResponse
    {
        $sourceLanguage = $request->request->get('source_language');
        $targetLanguage = $request->request->get('target_language');

        if (!$sourceLanguage || !$targetLanguage) {
            return new JsonResponse(['success' => false, 'message' => 'Missing parameters']);
        }

        $success = $this->translationService->duplicateTranslation(
            Product::class,
            $product->getId(),
            $sourceLanguage,
            $targetLanguage
        );

        if ($success) {
            return new JsonResponse(['success' => true, 'message' => 'Translation duplicated successfully']);
        } else {
            return new JsonResponse(['success' => false, 'message' => 'Failed to duplicate translation']);
        }
    }

    #[Route('/translation-stats', name: 'translation_stats', methods: ['GET'])]
    public function translationStats(): Response
    {
        $stats = $this->translationService->getTranslationStatistics();

        return $this->render('admin/product/translation_stats.html.twig', [
            'stats' => $stats
        ]);
    }

    private function handleProductSubmission(Request $request, Product $product, array $languages): Response
    {
        $data = $request->request->all();

        // Validate required fields
        $errors = $this->validateProductData($data);
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('admin_product_edit', ['id' => $product->getId()]);
        }

        // Set basic product data
        $product->setSku($data['sku']);
        $product->setPrice($data['price']);
        $product->setComparePrice($data['compare_price'] ?? null);
        $product->setCostPrice($data['cost_price'] ?? null);
        $product->setWeight($data['weight'] ?? null);
        $product->setStockQuantity((int) ($data['stock_quantity'] ?? 0));
        $product->setIsActive(isset($data['is_active']));
        $product->setIsFeatured(isset($data['is_featured']));
        $product->setIsDigital(isset($data['is_digital']));
        $product->setTrackStock(isset($data['track_stock']));

        // Set relations
        if (!empty($data['category_id'])) {
            $category = $this->categoryRepository->find($data['category_id']);
            $product->setCategory($category);
        }

        if (!empty($data['brand_id'])) {
            $brand = $this->brandRepository->find($data['brand_id']);
            $product->setBrand($brand);
        }

        // Handle dimensions
        if (!empty($data['dimensions'])) {
            $dimensions = [
                'length' => $data['dimensions']['length'] ?? null,
                'width' => $data['dimensions']['width'] ?? null,
                'height' => $data['dimensions']['height'] ?? null,
                'unit' => $data['dimensions']['unit'] ?? 'cm'
            ];
            $product->setDimensions($dimensions);
        }

        // Handle translations
        $translationsData = [];
        foreach ($languages as $language) {
            $langCode = $language->getCode();
            if (isset($data['translations'][$langCode])) {
                $translationsData[$langCode] = $data['translations'][$langCode];
            }
        }

        // Save product with translations
        $this->translationService->createOrUpdateProduct($product, $translationsData);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $this->addFlash('success', 'Product saved successfully');

        return $this->redirectToRoute('admin_product_edit', ['id' => $product->getId()]);
    }

    private function validateProductData(array $data): array
    {
        $errors = [];

        if (empty($data['sku'])) {
            $errors[] = 'SKU is required';
        }

        if (empty($data['price']) || !is_numeric($data['price']) || (float) $data['price'] < 0) {
            $errors[] = 'Valid price is required';
        }

        if (isset($data['compare_price']) && !empty($data['compare_price'])) {
            if (!is_numeric($data['compare_price']) || (float) $data['compare_price'] < 0) {
                $errors[] = 'Compare price must be a valid positive number';
            }
        }

        if (isset($data['cost_price']) && !empty($data['cost_price'])) {
            if (!is_numeric($data['cost_price']) || (float) $data['cost_price'] < 0) {
                $errors[] = 'Cost price must be a valid positive number';
            }
        }

        if (isset($data['weight']) && !empty($data['weight'])) {
            if (!is_numeric($data['weight']) || (float) $data['weight'] < 0) {
                $errors[] = 'Weight must be a valid positive number';
            }
        }

        if (isset($data['stock_quantity']) && (!is_numeric($data['stock_quantity']) || (int) $data['stock_quantity'] < 0)) {
            $errors[] = 'Stock quantity must be a valid positive integer';
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

        return $errors;
    }
}