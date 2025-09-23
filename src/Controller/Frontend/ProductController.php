<?php

namespace App\Controller\Frontend;

use App\Entity\Product;
use App\Entity\Review;
use App\Repository\ProductRepository;
use App\Service\LocaleService;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/{_locale}', requirements: ['_locale' => 'fr|en|es|de|it'], defaults: ['_locale' => 'fr'])]
class ProductController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository,
        private LocaleService $localeService,
        private CartService $cartService,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/product/{slug}', name: 'product_detail', methods: ['GET'])]
    public function detail(string $slug): Response
    {
        $locale = $this->localeService->getCurrentLocale();
        
        // Find product by slug in current locale
        $product = $this->productRepository->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->leftJoin('t.language', 'l')
            ->where('p.isActive = :active')
            ->andWhere('t.slug = :slug')
            ->andWhere('l.code = :locale')
            ->setParameter('active', true)
            ->setParameter('slug', $slug)
            ->setParameter('locale', $locale)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        // Get related products (same category)
        $relatedProducts = [];
        if ($product->getCategory()) {
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
        }

        // Calculate average rating and reviews count
        $reviews = $product->getReviews()->filter(function($review) {
            return $review->isApproved();
        });
        
        $averageRating = 0;
        $reviewsCount = $reviews->count();
        
        if ($reviewsCount > 0) {
            $totalRating = 0;
            foreach ($reviews as $review) {
                $totalRating += $review->getRating();
            }
            $averageRating = round($totalRating / $reviewsCount, 1);
        }

        return $this->render('product/detail.html.twig', [
            'product' => $product,
            'related_products' => $relatedProducts,
            'reviews' => $reviews,
            'average_rating' => $averageRating,
            'reviews_count' => $reviewsCount,
            'in_cart' => $this->cartService->hasProduct($product->getId()),
            'cart_quantity' => $this->cartService->getProductQuantity($product->getId()),
            'default_locale' => $this->localeService->getDefaultLanguage()->getCode()
        ]);
    }

    #[Route('/product/{id}/review', name: 'product_review', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addReview(int $id, Request $request): Response
    {
        $product = $this->productRepository->find($id);
        
        if (!$product || !$product->isActive()) {
            throw $this->createNotFoundException('Product not found');
        }

        $user = $this->getUser();
        
        // Check if user already reviewed this product
        $existingReview = $this->entityManager->getRepository(Review::class)
            ->findOneBy(['product' => $product, 'user' => $user]);
            
        if ($existingReview) {
            $this->addFlash('error', 'You have already reviewed this product');
            return $this->redirectToRoute('product_detail', [
                'slug' => $product->getTranslation($this->localeService->getCurrentLocale())->getSlug(),
                '_locale' => $request->getLocale()
            ]);
        }

        $title = $request->request->get('title');
        $comment = $request->request->get('comment');
        $rating = (int) $request->request->get('rating');

        if (!$comment || !$rating || $rating < 1 || $rating > 5) {
            $this->addFlash('error', 'Please provide a valid rating and comment');
            return $this->redirectToRoute('product_detail', [
                'slug' => $product->getTranslation($this->localeService->getCurrentLocale())->getSlug(),
                '_locale' => $request->getLocale()
            ]);
        }

        $review = new Review();
        $review->setProduct($product);
        $review->setUser($user);
        $review->setTitle($title);
        $review->setComment($comment);
        $review->setRating($rating);
        $review->setIsApproved(true); // Auto-approve for now
        $review->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($review);
        $this->entityManager->flush();

        $this->addFlash('success', 'Your review has been added successfully');
        
        return $this->redirectToRoute('product_detail', [
            'slug' => $product->getTranslation($this->localeService->getCurrentLocale())->getSlug(),
            '_locale' => $request->getLocale()
        ]);
    }

    #[Route('/api/product/{id}/quick-view', name: 'api_product_quick_view', methods: ['GET'])]
    public function quickView(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);
        
        if (!$product || !$product->isActive()) {
            return new JsonResponse(['error' => 'Product not found'], 404);
        }

        $locale = $this->localeService->getCurrentLocale();
        $translation = $product->getTranslation($locale);
        
        $images = [];
        foreach ($product->getImages() as $image) {
            $images[] = [
                'filename' => $image->getFilename(),
                'alt' => $image->getAlt() ?: $translation->getName()
            ];
        }

        return new JsonResponse([
            'id' => $product->getId(),
            'name' => $translation->getName(),
            'description' => $translation->getDescription(),
            'short_description' => $translation->getShortDescription(),
            'price' => $product->getPrice(),
            'compare_price' => $product->getComparePrice(),
            'sku' => $product->getSku(),
            'stock_quantity' => $product->getStockQuantity(),
            'images' => $images,
            'in_stock' => $product->getStockQuantity() > 0,
            'url' => $this->generateUrl('product_detail', [
                'slug' => $translation->getSlug(),
                '_locale' => $locale
            ])
        ]);
    }

    #[Route('/api/products/similar/{id}', name: 'api_product_similar', methods: ['GET'])]
    public function similarProducts(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);
        
        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], 404);
        }

        $locale = $this->localeService->getCurrentLocale();
        
        // Find similar products based on category and brand
        $similarProducts = $this->productRepository->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->leftJoin('t.language', 'l')
            ->where('p.isActive = :active')
            ->andWhere('l.code = :locale')
            ->andWhere('p.id != :productId')
            ->andWhere('(p.category = :category OR p.brand = :brand)')
            ->setParameter('active', true)
            ->setParameter('locale', $locale)
            ->setParameter('productId', $id)
            ->setParameter('category', $product->getCategory())
            ->setParameter('brand', $product->getBrand())
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($similarProducts as $similarProduct) {
            $translation = $similarProduct->getTranslation($locale);
            $firstImage = $similarProduct->getImages()->first();
            
            $result[] = [
                'id' => $similarProduct->getId(),
                'name' => $translation->getName(),
                'price' => $similarProduct->getPrice(),
                'compare_price' => $similarProduct->getComparePrice(),
                'image' => $firstImage ? $firstImage->getFilename() : null,
                'url' => $this->generateUrl('product_detail', [
                    'slug' => $translation->getSlug(),
                    '_locale' => $locale
                ])
            ];
        }

        return new JsonResponse($result);
    }
}