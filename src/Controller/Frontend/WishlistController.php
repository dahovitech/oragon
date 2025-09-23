<?php

namespace App\Controller\Frontend;

use App\Entity\Wishlist;
use App\Repository\ProductRepository;
use App\Repository\WishlistRepository;
use App\Service\LocaleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/{_locale}', requirements: ['_locale' => 'fr|en|es|de|it'], defaults: ['_locale' => 'fr'])]
#[IsGranted('ROLE_USER')]
class WishlistController extends AbstractController
{
    public function __construct(
        private WishlistRepository $wishlistRepository,
        private ProductRepository $productRepository,
        private LocaleService $localeService,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/wishlist', name: 'wishlist_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $wishlistItems = $this->wishlistRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->render('wishlist/index.html.twig', [
            'wishlist_items' => $wishlistItems,
            'default_locale' => $this->localeService->getDefaultLanguage()->getCode()
        ]);
    }

    #[Route('/api/wishlist/toggle', name: 'wishlist_toggle', methods: ['POST'])]
    public function toggle(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $productId = (int) $request->request->get('product_id');

        if (!$productId) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid product ID'], 400);
        }

        $product = $this->productRepository->find($productId);
        if (!$product || !$product->isActive()) {
            return new JsonResponse(['success' => false, 'message' => 'Product not found'], 404);
        }

        $wishlistItem = $this->wishlistRepository->findOneBy([
            'user' => $user,
            'product' => $product
        ]);

        if ($wishlistItem) {
            // Remove from wishlist
            $this->entityManager->remove($wishlistItem);
            $this->entityManager->flush();
            
            return new JsonResponse([
                'success' => true,
                'added' => false,
                'message' => 'Product removed from wishlist',
                'wishlist_count' => $this->wishlistRepository->count(['user' => $user])
            ]);
        } else {
            // Add to wishlist
            $wishlistItem = new Wishlist();
            $wishlistItem->setUser($user);
            $wishlistItem->setProduct($product);
            $wishlistItem->setCreatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($wishlistItem);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'added' => true,
                'message' => 'Product added to wishlist',
                'wishlist_count' => $this->wishlistRepository->count(['user' => $user])
            ]);
        }
    }

    #[Route('/api/wishlist/remove/{id}', name: 'wishlist_remove', methods: ['POST'])]
    public function remove(int $id): JsonResponse
    {
        $user = $this->getUser();
        
        $wishlistItem = $this->wishlistRepository->findOneBy([
            'id' => $id,
            'user' => $user
        ]);

        if (!$wishlistItem) {
            return new JsonResponse(['success' => false, 'message' => 'Wishlist item not found'], 404);
        }

        $this->entityManager->remove($wishlistItem);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Product removed from wishlist',
            'wishlist_count' => $this->wishlistRepository->count(['user' => $user])
        ]);
    }

    #[Route('/api/wishlist/clear', name: 'wishlist_clear', methods: ['POST'])]
    public function clear(): JsonResponse
    {
        $user = $this->getUser();
        
        $wishlistItems = $this->wishlistRepository->findBy(['user' => $user]);
        
        foreach ($wishlistItems as $item) {
            $this->entityManager->remove($item);
        }
        
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Wishlist cleared',
            'wishlist_count' => 0
        ]);
    }

    #[Route('/api/wishlist/count', name: 'wishlist_count', methods: ['GET'])]
    public function count(): JsonResponse
    {
        $user = $this->getUser();
        $count = $this->wishlistRepository->count(['user' => $user]);

        return new JsonResponse(['count' => $count]);
    }

    #[Route('/api/wishlist/check/{productId}', name: 'wishlist_check', methods: ['GET'])]
    public function check(int $productId): JsonResponse
    {
        $user = $this->getUser();
        
        $wishlistItem = $this->wishlistRepository->findOneBy([
            'user' => $user,
            'product' => $productId
        ]);

        return new JsonResponse(['in_wishlist' => $wishlistItem !== null]);
    }
}