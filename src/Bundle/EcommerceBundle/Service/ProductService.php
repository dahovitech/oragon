<?php

namespace App\Bundle\EcommerceBundle\Service;

use App\Bundle\EcommerceBundle\Entity\Product;
use App\Bundle\EcommerceBundle\Entity\Review;
use App\Bundle\EcommerceBundle\Repository\ProductRepository;
use App\Bundle\EcommerceBundle\Repository\ReviewRepository;
use App\Bundle\EcommerceBundle\Repository\OrderItemRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProductService
{
    private EntityManagerInterface $entityManager;
    private ProductRepository $productRepository;
    private ReviewRepository $reviewRepository;
    private OrderItemRepository $orderItemRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProductRepository $productRepository,
        ReviewRepository $reviewRepository,
        OrderItemRepository $orderItemRepository
    ) {
        $this->entityManager = $entityManager;
        $this->productRepository = $productRepository;
        $this->reviewRepository = $reviewRepository;
        $this->orderItemRepository = $orderItemRepository;
    }

    /**
     * Get featured products
     */
    public function getFeaturedProducts(int $limit = 8): array
    {
        return $this->productRepository->findFeatured($limit);
    }

    /**
     * Search products
     */
    public function searchProducts(string $query, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        if (!empty($query)) {
            $filters['search'] = $query;
        }

        return $this->productRepository->findWithFilters($filters, $limit, $offset);
    }

    /**
     * Get products by category
     */
    public function getProductsByCategory($category, int $limit = 20, int $offset = 0): array
    {
        return $this->productRepository->findByCategory($category, $limit, $offset);
    }

    /**
     * Get product by slug
     */
    public function getProductBySlug(string $slug): ?Product
    {
        return $this->productRepository->findOneBySlug($slug);
    }

    /**
     * Increment product view count
     */
    public function incrementViewCount(Product $product): void
    {
        $product->setViewCount($product->getViewCount() + 1);
        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }

    /**
     * Get product reviews with statistics
     */
    public function getProductReviews(Product $product, int $limit = null): array
    {
        $reviews = $this->reviewRepository->findApprovedByProduct($product, $limit);
        $averageRating = $this->reviewRepository->getAverageRating($product);
        $reviewCount = $this->reviewRepository->getReviewCount($product);
        $ratingDistribution = $this->reviewRepository->getRatingDistribution($product);

        return [
            'reviews' => $reviews,
            'average_rating' => $averageRating,
            'review_count' => $reviewCount,
            'rating_distribution' => $ratingDistribution,
        ];
    }

    /**
     * Get related products based on category and tags
     */
    public function getRelatedProducts(Product $product, int $limit = 4): array
    {
        $relatedProducts = [];

        // First, try to get products from the same category
        if ($product->getCategory()) {
            $categoryProducts = $this->productRepository->findByCategory($product->getCategory(), $limit * 2);
            
            foreach ($categoryProducts as $categoryProduct) {
                if ($categoryProduct->getId() !== $product->getId()) {
                    $relatedProducts[] = $categoryProduct;
                }
            }
        }

        // If we don't have enough, get featured products
        if (count($relatedProducts) < $limit) {
            $featuredProducts = $this->productRepository->findFeatured($limit);
            
            foreach ($featuredProducts as $featuredProduct) {
                if ($featuredProduct->getId() !== $product->getId() && 
                    !in_array($featuredProduct, $relatedProducts, true)) {
                    $relatedProducts[] = $featuredProduct;
                }
            }
        }

        return array_slice($relatedProducts, 0, $limit);
    }

    /**
     * Get frequently bought together products
     */
    public function getFrequentlyBoughtTogether(Product $product, int $limit = 3): array
    {
        return $this->orderItemRepository->findFrequentlyBoughtTogether($product->getSku(), $limit);
    }

    /**
     * Check if product is in stock
     */
    public function isInStock(Product $product, int $quantity = 1): bool
    {
        if (!$product->isTrackStock()) {
            return true;
        }

        return $product->getStock() >= $quantity;
    }

    /**
     * Reserve stock for product
     */
    public function reserveStock(Product $product, int $quantity): bool
    {
        if (!$this->isInStock($product, $quantity)) {
            return false;
        }

        if ($product->isTrackStock()) {
            $product->setStock($product->getStock() - $quantity);
            $this->entityManager->persist($product);
            $this->entityManager->flush();
        }

        return true;
    }

    /**
     * Release reserved stock
     */
    public function releaseStock(Product $product, int $quantity): void
    {
        if ($product->isTrackStock()) {
            $product->setStock($product->getStock() + $quantity);
            $this->entityManager->persist($product);
            $this->entityManager->flush();
        }
    }

    /**
     * Record product sale
     */
    public function recordSale(Product $product, int $quantity): void
    {
        $product->setSalesCount($product->getSalesCount() + $quantity);
        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }

    /**
     * Get low stock products
     */
    public function getLowStockProducts(int $threshold = 5): array
    {
        return $this->productRepository->findWithLowStock($threshold);
    }

    /**
     * Get best selling products
     */
    public function getBestSellingProducts(int $limit = 10, \DateTimeInterface $from = null, \DateTimeInterface $to = null): array
    {
        return $this->orderItemRepository->getBestSellingProducts($limit, $from, $to);
    }

    /**
     * Get product recommendations for user
     */
    public function getRecommendationsForUser($user, int $limit = 6): array
    {
        // Simple recommendation logic - can be enhanced with ML algorithms
        
        // Get user's recent orders to understand preferences
        // For now, return popular/featured products
        return $this->getFeaturedProducts($limit);
    }

    /**
     * Calculate product discount percentage
     */
    public function calculateDiscountPercentage(Product $product): ?float
    {
        if (!$product->getComparePrice()) {
            return null;
        }

        $price = (float)$product->getPrice();
        $comparePrice = (float)$product->getComparePrice();

        if ($comparePrice <= $price) {
            return null;
        }

        return round((($comparePrice - $price) / $comparePrice) * 100, 2);
    }

    /**
     * Check if product has discount
     */
    public function hasDiscount(Product $product): bool
    {
        return $this->calculateDiscountPercentage($product) !== null;
    }

    /**
     * Get product availability status
     */
    public function getAvailabilityStatus(Product $product): string
    {
        if (!$product->isActive()) {
            return 'unavailable';
        }

        if (!$product->isTrackStock()) {
            return 'in_stock';
        }

        $stock = $product->getStock();
        
        if ($stock <= 0) {
            return 'out_of_stock';
        }
        
        if ($stock <= 5) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    /**
     * Get products that need attention (low stock, inactive, etc.)
     */
    public function getProductsNeedingAttention(): array
    {
        $lowStock = $this->getLowStockProducts();
        
        $inactiveProducts = $this->productRepository->findBy(['isActive' => false]);

        return [
            'low_stock' => $lowStock,
            'inactive' => $inactiveProducts,
        ];
    }

    /**
     * Generate product URL slug
     */
    public function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        // Ensure uniqueness
        $baseSlug = $slug;
        $counter = 1;
        
        while ($this->productRepository->findOneBySlug($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Update product SEO data
     */
    public function updateSeoData(Product $product): void
    {
        if (!$product->getMetaTitle()) {
            $product->setMetaTitle(substr($product->getName(), 0, 60));
        }

        if (!$product->getMetaDescription()) {
            $description = $product->getShortDescription() ?: $product->getDescription();
            if ($description) {
                $product->setMetaDescription(substr(strip_tags($description), 0, 160));
            }
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }
}