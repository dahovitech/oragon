<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductTranslation;
use App\Entity\Language;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\BrandRepository;
use App\Repository\LanguageRepository;
use App\Service\TranslationService;
use App\Service\LocaleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/products', name: 'admin_product_')]
#[IsGranted('ROLE_ADMIN')]
class ProductController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private BrandRepository $brandRepository,
        private LanguageRepository $languageRepository,
        private TranslationService $translationService,
        private LocaleService $localeService
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search');
        $category = $request->query->get('category');
        $brand = $request->query->get('brand');
        $status = $request->query->get('status');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        
        $queryBuilder = $this->productRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.brand', 'b');

        if ($search) {
            $queryBuilder->leftJoin('p.translations', 't')
                        ->andWhere('t.name LIKE :search OR p.sku LIKE :search')
                        ->setParameter('search', '%' . $search . '%');
        }

        if ($category) {
            $queryBuilder->andWhere('p.category = :category')
                        ->setParameter('category', $category);
        }

        if ($brand) {
            $queryBuilder->andWhere('p.brand = :brand')
                        ->setParameter('brand', $brand);
        }

        if ($status !== null) {
            $queryBuilder->andWhere('p.isActive = :status')
                        ->setParameter('status', (bool) $status);
        }

        $totalQuery = clone $queryBuilder;
        $totalCount = $totalQuery->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        $products = $queryBuilder->orderBy('p.createdAt', 'DESC')
                                ->setFirstResult(($page - 1) * $limit)
                                ->setMaxResults($limit)
                                ->getQuery()
                                ->getResult();

        $categories = $this->categoryRepository->findBy([], ['sortOrder' => 'ASC']);
        $brands = $this->brandRepository->findBy([], ['name' => 'ASC']);

        return $this->render('admin/product/index.html.twig', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'brand' => $brand,
                'status' => $status,
            ],
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($totalCount / $limit),
                'total_items' => $totalCount,
            ],
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $product = new Product();
        
        if ($request->isMethod('POST')) {
            return $this->handleProductForm($product, $request);
        }

        $categories = $this->categoryRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);
        $brands = $this->brandRepository->findBy(['isActive' => true], ['name' => 'ASC']);
        $languages = $this->localeService->getActiveLanguages();

        return $this->render('admin/product/form.html.twig', [
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands,
            'languages' => $languages,
            'is_edit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $product = $this->productRepository->find($id);
        
        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        if ($request->isMethod('POST')) {
            return $this->handleProductForm($product, $request);
        }

        $categories = $this->categoryRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);
        $brands = $this->brandRepository->findBy(['isActive' => true], ['name' => 'ASC']);
        $languages = $this->localeService->getActiveLanguages();

        // Get completion stats for translations
        $translationStats = $this->translationService->getCompletionStats($product);

        return $this->render('admin/product/form.html.twig', [
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands,
            'languages' => $languages,
            'translation_stats' => $translationStats,
            'is_edit' => true,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $product = $this->productRepository->find($id);
        
        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        $translationStats = $this->translationService->getCompletionStats($product);
        $missingTranslations = $this->translationService->getMissingTranslations($product);

        return $this->render('admin/product/show.html.twig', [
            'product' => $product,
            'translation_stats' => $translationStats,
            'missing_translations' => $missingTranslations,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $product = $this->productRepository->find($id);
        
        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        if ($this->isCsrfTokenValid('delete' . $product->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($product);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Product deleted successfully');
        }

        return $this->redirectToRoute('admin_product_index');
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);
        
        if (!$product) {
            return new JsonResponse(['success' => false, 'message' => 'Product not found'], 404);
        }

        $product->setIsActive(!$product->isActive());
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'isActive' => $product->isActive(),
            'message' => $product->isActive() ? 'Product activated' : 'Product deactivated'
        ]);
    }

    #[Route('/bulk-action', name: 'bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request): JsonResponse
    {
        $action = $request->request->get('action');
        $productIds = $request->request->all('product_ids');

        if (empty($productIds)) {
            return new JsonResponse(['success' => false, 'message' => 'No products selected'], 400);
        }

        $products = $this->productRepository->findBy(['id' => $productIds]);
        $count = 0;

        switch ($action) {
            case 'activate':
                foreach ($products as $product) {
                    $product->setIsActive(true);
                    $count++;
                }
                $message = "$count products activated";
                break;

            case 'deactivate':
                foreach ($products as $product) {
                    $product->setIsActive(false);
                    $count++;
                }
                $message = "$count products deactivated";
                break;

            case 'delete':
                foreach ($products as $product) {
                    $this->entityManager->remove($product);
                    $count++;
                }
                $message = "$count products deleted";
                break;

            default:
                return new JsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
        }

        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => $message]);
    }

    private function handleProductForm(Product $product, Request $request): Response
    {
        $isNew = $product->getId() === null;
        
        // Handle basic product data
        $product->setSku($request->request->get('sku'));
        $product->setPrice((float) $request->request->get('price'));
        $product->setComparePrice($request->request->get('compare_price') ? (float) $request->request->get('compare_price') : null);
        $product->setCostPrice($request->request->get('cost_price') ? (float) $request->request->get('cost_price') : null);
        $product->setWeight($request->request->get('weight') ? (float) $request->request->get('weight') : null);
        $product->setStockQuantity($request->request->get('stock_quantity') ? (int) $request->request->get('stock_quantity') : null);
        $product->setIsActive((bool) $request->request->get('is_active'));
        $product->setIsFeatured((bool) $request->request->get('is_featured'));
        $product->setIsDigital((bool) $request->request->get('is_digital'));

        // Handle relationships
        if ($categoryId = $request->request->get('category_id')) {
            $category = $this->categoryRepository->find($categoryId);
            $product->setCategory($category);
        }

        if ($brandId = $request->request->get('brand_id')) {
            $brand = $this->brandRepository->find($brandId);
            $product->setBrand($brand);
        }

        // Handle dimensions
        $dimensions = [
            'length' => $request->request->get('length'),
            'width' => $request->request->get('width'),
            'height' => $request->request->get('height'),
        ];
        if (array_filter($dimensions)) {
            $product->setDimensions($dimensions);
        }

        if ($isNew) {
            $this->entityManager->persist($product);
        }

        $this->entityManager->flush();

        // Handle translations
        $translations = $request->request->all('translations');
        if ($translations) {
            foreach ($translations as $languageCode => $translationData) {
                if (!empty(array_filter($translationData))) {
                    $this->translationService->setTranslation($product, $languageCode, $translationData);
                }
            }
        }

        $this->addFlash('success', $isNew ? 'Product created successfully' : 'Product updated successfully');

        return $this->redirectToRoute('admin_product_show', ['id' => $product->getId()]);
    }
}