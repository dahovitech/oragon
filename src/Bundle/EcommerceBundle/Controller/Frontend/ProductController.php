<?php

namespace App\Bundle\EcommerceBundle\Controller\Frontend;

use App\Bundle\CoreBundle\Entity\Category;
use App\Bundle\EcommerceBundle\Entity\Product;
use App\Bundle\EcommerceBundle\Service\ProductService;
use App\Bundle\EcommerceBundle\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/products', name: 'ecommerce_product_')]
class ProductController extends AbstractController
{
    private ProductService $productService;
    private ProductRepository $productRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        ProductService $productService,
        ProductRepository $productRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->productService = $productService;
        $this->productRepository = $productRepository;
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 12;
        $offset = ($page - 1) * $limit;

        $filters = [
            'category' => $request->query->get('category'),
            'search' => $request->query->get('search'),
            'min_price' => $request->query->get('min_price'),
            'max_price' => $request->query->get('max_price'),
            'sort' => $request->query->get('sort', 'name'),
            'order' => $request->query->get('order', 'ASC'),
            'featured' => $request->query->getBoolean('featured'),
            'in_stock' => $request->query->getBoolean('in_stock'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');

        $products = $this->productService->searchProducts('', $filters, $limit, $offset);
        $totalProducts = $this->productRepository->countByFilters($filters);
        $totalPages = ceil($totalProducts / $limit);

        // Get categories for filter
        $categories = $this->entityManager->getRepository(Category::class)->findAll();

        return $this->render('@Ecommerce/frontend/product/list.html.twig', [
            'products' => $products,
            'categories' => $categories,
            'filters' => $filters,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $totalProducts,
                'per_page' => $limit,
            ],
        ]);
    }

    #[Route('/category/{slug}', name: 'category', methods: ['GET'])]
    public function category(Category $category, Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 12;
        $offset = ($page - 1) * $limit;

        $products = $this->productService->getProductsByCategory($category, $limit, $offset);
        
        // Get total count for pagination
        $totalProducts = $this->productRepository->countByFilters(['category' => $category]);
        $totalPages = ceil($totalProducts / $limit);

        return $this->render('@Ecommerce/frontend/product/category.html.twig', [
            'category' => $category,
            'products' => $products,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $totalProducts,
                'per_page' => $limit,
            ],
        ]);
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $query = $request->query->get('q', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 12;
        $offset = ($page - 1) * $limit;

        if (empty($query)) {
            return $this->redirectToRoute('ecommerce_product_list');
        }

        $products = $this->productService->searchProducts($query, [], $limit, $offset);
        $totalProducts = $this->productRepository->countByFilters(['search' => $query]);
        $totalPages = ceil($totalProducts / $limit);

        return $this->render('@Ecommerce/frontend/product/search.html.twig', [
            'query' => $query,
            'products' => $products,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $totalProducts,
                'per_page' => $limit,
            ],
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $product = $this->productService->getProductBySlug($slug);

        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        // Increment view count
        $this->productService->incrementViewCount($product);

        // Get product reviews and statistics
        $reviewData = $this->productService->getProductReviews($product, 10);

        // Get related products
        $relatedProducts = $this->productService->getRelatedProducts($product, 4);

        // Get frequently bought together
        $frequentlyBought = $this->productService->getFrequentlyBoughtTogether($product, 3);

        // Check stock availability
        $availabilityStatus = $this->productService->getAvailabilityStatus($product);

        // Calculate discount if any
        $discountPercentage = $this->productService->calculateDiscountPercentage($product);

        return $this->render('@Ecommerce/frontend/product/show.html.twig', [
            'product' => $product,
            'reviews' => $reviewData['reviews'],
            'average_rating' => $reviewData['average_rating'],
            'review_count' => $reviewData['review_count'],
            'rating_distribution' => $reviewData['rating_distribution'],
            'related_products' => $relatedProducts,
            'frequently_bought' => $frequentlyBought,
            'availability_status' => $availabilityStatus,
            'discount_percentage' => $discountPercentage,
        ]);
    }

    #[Route('/{slug}/reviews', name: 'reviews', methods: ['GET'])]
    public function reviews(string $slug, Request $request): Response
    {
        $product = $this->productService->getProductBySlug($slug);

        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;
        $rating = $request->query->getInt('rating');

        // Get reviews with filtering
        if ($rating) {
            $reviews = $this->entityManager->getRepository(\App\Bundle\EcommerceBundle\Entity\Review::class)
                ->findByRating($product, $rating);
        } else {
            $reviewData = $this->productService->getProductReviews($product);
            $reviews = $reviewData['reviews'];
        }

        // Get review statistics
        $reviewData = $this->productService->getProductReviews($product);

        return $this->render('@Ecommerce/frontend/product/reviews.html.twig', [
            'product' => $product,
            'reviews' => $reviews,
            'average_rating' => $reviewData['average_rating'],
            'review_count' => $reviewData['review_count'],
            'rating_distribution' => $reviewData['rating_distribution'],
            'filter_rating' => $rating,
        ]);
    }

    #[Route('/ajax/quick-view/{id}', name: 'quick_view', methods: ['GET'])]
    public function quickView(Product $product): Response
    {
        // Check stock availability
        $availabilityStatus = $this->productService->getAvailabilityStatus($product);

        // Calculate discount if any
        $discountPercentage = $this->productService->calculateDiscountPercentage($product);

        return $this->render('@Ecommerce/frontend/product/quick_view.html.twig', [
            'product' => $product,
            'availability_status' => $availabilityStatus,
            'discount_percentage' => $discountPercentage,
        ]);
    }
}