<?php

namespace App\Controller\Frontend;

use App\Entity\Product;
use App\Entity\Category;
use App\Entity\Brand;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\BrandRepository;
use App\Service\LocaleService;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}', requirements: ['_locale' => 'fr|en|es|de|it'], defaults: ['_locale' => 'fr'])]
class CatalogController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private BrandRepository $brandRepository,
        private LocaleService $localeService,
        private CartService $cartService,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/products', name: 'catalog_products', methods: ['GET'])]
    public function products(Request $request): Response
    {
        $locale = $this->localeService->getCurrentLocale();
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(50, max(1, $request->query->getInt('limit', 12)));
        
        // Filters
        $categoryId = $request->query->getInt('category');
        $brandId = $request->query->getInt('brand');
        $search = $request->query->get('search', '');
        $minPrice = $request->query->get('min_price');
        $maxPrice = $request->query->get('max_price');
        $sortBy = $request->query->get('sort', 'name'); // name, price_asc, price_desc, newest

        // Build query
        $queryBuilder = $this->productRepository->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->leftJoin('t.language', 'l')
            ->where('p.isActive = :active')
            ->andWhere('l.code = :locale')
            ->setParameter('active', true)
            ->setParameter('locale', $locale);

        // Apply filters
        if ($categoryId) {
            $queryBuilder->andWhere('p.category = :categoryId')
                        ->setParameter('categoryId', $categoryId);
        }

        if ($brandId) {
            $queryBuilder->andWhere('p.brand = :brandId')
                        ->setParameter('brandId', $brandId);
        }

        if ($search) {
            $queryBuilder->andWhere('t.name LIKE :search OR t.description LIKE :search')
                        ->setParameter('search', '%' . $search . '%');
        }

        if ($minPrice) {
            $queryBuilder->andWhere('p.price >= :minPrice')
                        ->setParameter('minPrice', $minPrice);
        }

        if ($maxPrice) {
            $queryBuilder->andWhere('p.price <= :maxPrice')
                        ->setParameter('maxPrice', $maxPrice);
        }

        // Apply sorting
        switch ($sortBy) {
            case 'price_asc':
                $queryBuilder->orderBy('p.price', 'ASC');
                break;
            case 'price_desc':
                $queryBuilder->orderBy('p.price', 'DESC');
                break;
            case 'newest':
                $queryBuilder->orderBy('p.createdAt', 'DESC');
                break;
            case 'name':
            default:
                $queryBuilder->orderBy('t.name', 'ASC');
                break;
        }

        // Get total count for pagination
        $totalQuery = clone $queryBuilder;
        $totalCount = $totalQuery->select('COUNT(DISTINCT p.id)')->getQuery()->getSingleScalarResult();

        // Apply pagination
        $queryBuilder->setFirstResult(($page - 1) * $limit)
                    ->setMaxResults($limit);

        $products = $queryBuilder->getQuery()->getResult();

        // Get filter options
        $categories = $this->categoryRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);
        $brands = $this->brandRepository->findBy(['isActive' => true], ['name' => 'ASC']);

        return $this->render('frontend/catalog/products.html.twig', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'filters' => [
                'category' => $categoryId,
                'brand' => $brandId,
                'search' => $search,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'sort' => $sortBy,
            ],
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($totalCount / $limit),
                'total_items' => $totalCount,
                'limit' => $limit,
            ]
        ]);
    }

    #[Route('/category/{id}', name: 'catalog_category', methods: ['GET'])]
    public function category(int $id, Request $request): Response
    {
        $category = $this->categoryRepository->find($id);
        
        if (!$category || !$category->isActive()) {
            throw $this->createNotFoundException('Category not found');
        }

        // Forward to products route with category filter
        $request->query->set('category', $id);
        return $this->forward(self::class . '::products', [], $request->query->all());
    }

    #[Route('/brand/{id}', name: 'catalog_brand', methods: ['GET'])]
    public function brand(int $id, Request $request): Response
    {
        $brand = $this->brandRepository->find($id);
        
        if (!$brand || !$brand->isActive()) {
            throw $this->createNotFoundException('Brand not found');
        }

        // Forward to products route with brand filter
        $request->query->set('brand', $id);
        return $this->forward(self::class . '::products', [], $request->query->all());
    }

    #[Route('/product/{id}', name: 'catalog_product_detail', methods: ['GET'])]
    public function productDetail(int $id): Response
    {
        $product = $this->productRepository->find($id);
        
        if (!$product || !$product->isActive()) {
            throw $this->createNotFoundException('Product not found');
        }

        $locale = $this->localeService->getCurrentLocale();
        
        // Get related products (same category)
        $relatedProducts = $this->productRepository->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->leftJoin('t.language', 'l')
            ->where('p.isActive = :active')
            ->andWhere('p.category = :category')
            ->andWhere('p.id != :productId')
            ->andWhere('l.code = :locale')
            ->setParameter('active', true)
            ->setParameter('category', $product->getCategory())
            ->setParameter('productId', $product->getId())
            ->setParameter('locale', $locale)
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();

        return $this->render('frontend/catalog/product_detail.html.twig', [
            'product' => $product,
            'related_products' => $relatedProducts,
            'in_cart' => $this->cartService->hasProduct($product->getId()),
            'cart_quantity' => $this->cartService->getProductQuantity($product->getId()),
        ]);
    }

    #[Route('/search', name: 'catalog_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return $this->render('frontend/catalog/search.html.twig', [
                'query' => $query,
                'products' => [],
                'error' => 'Search query must be at least 2 characters long'
            ]);
        }

        $locale = $this->localeService->getCurrentLocale();
        
        $products = $this->productRepository->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->leftJoin('t.language', 'l')
            ->where('p.isActive = :active')
            ->andWhere('l.code = :locale')
            ->andWhere('t.name LIKE :query OR t.description LIKE :query OR p.sku LIKE :query')
            ->setParameter('active', true)
            ->setParameter('locale', $locale)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.name', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        return $this->render('frontend/catalog/search.html.twig', [
            'query' => $query,
            'products' => $products,
            'count' => count($products)
        ]);
    }

    #[Route('/api/search/suggestions', name: 'api_search_suggestions', methods: ['GET'])]
    public function searchSuggestions(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return new JsonResponse([]);
        }

        $locale = $this->localeService->getCurrentLocale();
        
        $products = $this->productRepository->createQueryBuilder('p')
            ->select('t.name', 'p.id', 'p.price')
            ->leftJoin('p.translations', 't')
            ->leftJoin('t.language', 'l')
            ->where('p.isActive = :active')
            ->andWhere('l.code = :locale')
            ->andWhere('t.name LIKE :query')
            ->setParameter('active', true)
            ->setParameter('locale', $locale)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.name', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return new JsonResponse($products);
    }

    #[Route('/categories', name: 'catalog_categories', methods: ['GET'])]
    public function categories(): Response
    {
        $locale = $this->localeService->getCurrentLocale();
        
        $categories = $this->categoryRepository->createQueryBuilder('c')
            ->leftJoin('c.translations', 't')
            ->leftJoin('t.language', 'l')
            ->where('c.isActive = :active')
            ->andWhere('l.code = :locale')
            ->setParameter('active', true)
            ->setParameter('locale', $locale)
            ->orderBy('c.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('frontend/catalog/categories.html.twig', [
            'categories' => $categories
        ]);
    }

    #[Route('/brands', name: 'catalog_brands', methods: ['GET'])]
    public function brands(): Response
    {
        $locale = $this->localeService->getCurrentLocale();
        
        $brands = $this->brandRepository->createQueryBuilder('b')
            ->leftJoin('b.translations', 't')
            ->leftJoin('t.language', 'l')
            ->where('b.isActive = :active')
            ->andWhere('l.code = :locale')
            ->setParameter('active', true)
            ->setParameter('locale', $locale)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('frontend/catalog/brands.html.twig', [
            'brands' => $brands
        ]);
    }
}