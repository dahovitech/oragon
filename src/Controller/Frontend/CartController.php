<?php

namespace App\Controller\Frontend;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\ShippingMethodRepository;
use App\Repository\PaymentMethodRepository;
use App\Service\CartService;
use App\Service\LocaleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}', requirements: ['_locale' => 'fr|en|es|de|it'], defaults: ['_locale' => 'fr'])]
class CartController extends AbstractController
{
    public function __construct(
        private CartService $cartService,
        private ProductRepository $productRepository,
        private ShippingMethodRepository $shippingMethodRepository,
        private PaymentMethodRepository $paymentMethodRepository,
        private LocaleService $localeService
    ) {
    }

    #[Route('/cart', name: 'cart_index', methods: ['GET'])]
    public function index(): Response
    {
        // Validate cart before displaying
        $errors = $this->cartService->validateCart();
        $cart = $this->cartService->getCartWithDetails();

        if ($errors) {
            foreach ($errors as $error) {
                $this->addFlash('warning', $error);
            }
        }

        return $this->render('frontend/cart/index.html.twig', [
            'cart' => $cart,
            'errors' => $errors
        ]);
    }

    #[Route('/cart/add', name: 'cart_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        $productId = $request->request->getInt('product_id');
        $quantity = max(1, $request->request->getInt('quantity', 1));
        $options = $request->request->all('options') ?? [];

        $product = $this->productRepository->find($productId);
        
        if (!$product || !$product->isActive()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Product not found or not available'
            ], 404);
        }

        $success = $this->cartService->addProduct($productId, $quantity, $options);

        if ($success) {
            $cart = $this->cartService->getCartWithDetails();
            $locale = $this->localeService->getCurrentLocale();
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Product added to cart',
                'cart' => [
                    'items_count' => $this->cartService->getItemsCount(),
                    'total' => $this->localeService->formatCurrency($cart['totals']['total'], $locale)
                ]
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'message' => 'Failed to add product to cart'
        ], 400);
    }

    #[Route('/cart/update', name: 'cart_update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        $itemKey = $request->request->get('item_key');
        $quantity = $request->request->getInt('quantity');

        if (!$itemKey) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid item key'
            ], 400);
        }

        $success = $this->cartService->updateQuantity($itemKey, $quantity);

        if ($success) {
            $cart = $this->cartService->getCartWithDetails();
            $locale = $this->localeService->getCurrentLocale();
            
            return new JsonResponse([
                'success' => true,
                'message' => $quantity > 0 ? 'Cart updated' : 'Item removed from cart',
                'cart' => [
                    'items_count' => $this->cartService->getItemsCount(),
                    'total' => $this->localeService->formatCurrency($cart['totals']['total'], $locale)
                ]
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'message' => 'Failed to update cart'
        ], 400);
    }

    #[Route('/cart/remove', name: 'cart_remove', methods: ['POST'])]
    public function remove(Request $request): JsonResponse
    {
        $itemKey = $request->request->get('item_key');

        if (!$itemKey) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid item key'
            ], 400);
        }

        $success = $this->cartService->removeItem($itemKey);

        if ($success) {
            $cart = $this->cartService->getCartWithDetails();
            $locale = $this->localeService->getCurrentLocale();
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Item removed from cart',
                'cart' => [
                    'items_count' => $this->cartService->getItemsCount(),
                    'total' => $this->localeService->formatCurrency($cart['totals']['total'], $locale),
                    'is_empty' => $this->cartService->isEmpty()
                ]
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'message' => 'Failed to remove item from cart'
        ], 400);
    }

    #[Route('/cart/clear', name: 'cart_clear', methods: ['POST'])]
    public function clear(): JsonResponse
    {
        $this->cartService->clear();

        return new JsonResponse([
            'success' => true,
            'message' => 'Cart cleared',
            'cart' => [
                'items_count' => 0,
                'total' => $this->localeService->formatCurrency(0, $this->localeService->getCurrentLocale()),
                'is_empty' => true
            ]
        ]);
    }

    #[Route('/cart/widget', name: 'cart_widget', methods: ['GET'])]
    public function widget(): JsonResponse
    {
        $cart = $this->cartService->getCartWithDetails();
        $locale = $this->localeService->getCurrentLocale();

        $items = [];
        foreach ($cart['items'] as $itemKey => $item) {
            $product = $item['product'];
            $translation = $product->getTranslation($locale);
            
            $items[] = [
                'key' => $itemKey,
                'name' => $translation ? $translation->getName() : $product->getSku(),
                'quantity' => $item['quantity'],
                'unit_price' => $this->localeService->formatCurrency($item['unit_price'], $locale),
                'total_price' => $this->localeService->formatCurrency($item['total_price'], $locale),
                'product_url' => $this->generateUrl('catalog_product_detail', ['id' => $product->getId()])
            ];
        }

        return new JsonResponse([
            'items' => $items,
            'totals' => [
                'items_count' => $this->cartService->getItemsCount(),
                'subtotal' => $this->localeService->formatCurrency($cart['totals']['subtotal'], $locale),
                'total' => $this->localeService->formatCurrency($cart['totals']['total'], $locale)
            ],
            'is_empty' => $this->cartService->isEmpty()
        ]);
    }

    #[Route('/checkout', name: 'checkout_index', methods: ['GET'])]
    public function checkout(): Response
    {
        if ($this->cartService->isEmpty()) {
            $this->addFlash('warning', 'Your cart is empty');
            return $this->redirectToRoute('cart_index');
        }

        // Validate cart before checkout
        $errors = $this->cartService->validateCart();
        
        if ($errors) {
            foreach ($errors as $error) {
                $this->addFlash('warning', $error);
            }
        }

        $cart = $this->cartService->getCartWithDetails();
        $shippingMethods = $this->shippingMethodRepository->findAvailableForOrder(
            $cart['totals']['subtotal'],
            $cart['totals']['total_weight']
        );
        $paymentMethods = $this->paymentMethodRepository->findActive();

        return $this->render('frontend/cart/checkout.html.twig', [
            'cart' => $cart,
            'shipping_methods' => $shippingMethods,
            'payment_methods' => $paymentMethods,
            'user' => $this->getUser()
        ]);
    }

    #[Route('/checkout/shipping', name: 'checkout_calculate_shipping', methods: ['POST'])]
    public function calculateShipping(Request $request): JsonResponse
    {
        $shippingMethodId = $request->request->getInt('shipping_method_id');
        $shippingMethod = $this->shippingMethodRepository->find($shippingMethodId);

        if (!$shippingMethod || !$shippingMethod->isActive()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid shipping method'
            ], 400);
        }

        $cart = $this->cartService->getCartWithDetails();
        $totals = $this->cartService->applyShipping($shippingMethod);
        $locale = $this->localeService->getCurrentLocale();

        if ($totals['shipping'] < 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Shipping method not available for this order'
            ], 400);
        }

        return new JsonResponse([
            'success' => true,
            'totals' => [
                'subtotal' => $this->localeService->formatCurrency($totals['subtotal'], $locale),
                'shipping' => $this->localeService->formatCurrency($totals['shipping'], $locale),
                'tax' => $this->localeService->formatCurrency($totals['tax'], $locale),
                'discount' => $this->localeService->formatCurrency($totals['discount'], $locale),
                'total' => $this->localeService->formatCurrency($totals['total'], $locale)
            ],
            'shipping_method' => [
                'id' => $shippingMethod->getId(),
                'name' => $shippingMethod->getName($locale),
                'description' => $shippingMethod->getDescription($locale),
                'estimated_days' => $shippingMethod->getEstimatedDays()
            ]
        ]);
    }

    #[Route('/api/cart/count', name: 'api_cart_count', methods: ['GET'])]
    public function getCartCount(): JsonResponse
    {
        return new JsonResponse([
            'count' => $this->cartService->getItemsCount()
        ]);
    }

    #[Route('/api/cart/mini', name: 'api_cart_mini', methods: ['GET'])]
    public function getMiniCart(): JsonResponse
    {
        $cart = $this->cartService->getCartWithDetails();
        $locale = $this->localeService->getCurrentLocale();

        $items = [];
        $maxItems = 3; // Show only first 3 items in mini cart
        $itemCount = 0;

        foreach ($cart['items'] as $itemKey => $item) {
            if ($itemCount >= $maxItems) break;
            
            $product = $item['product'];
            $translation = $product->getTranslation($locale);
            
            $items[] = [
                'key' => $itemKey,
                'name' => $translation ? $translation->getName() : $product->getSku(),
                'quantity' => $item['quantity'],
                'total_price' => $this->localeService->formatCurrency($item['total_price'], $locale)
            ];
            
            $itemCount++;
        }

        return new JsonResponse([
            'items' => $items,
            'total_items' => $this->cartService->getItemsCount(),
            'has_more' => count($cart['items']) > $maxItems,
            'total' => $this->localeService->formatCurrency($cart['totals']['total'], $locale),
            'is_empty' => $this->cartService->isEmpty()
        ]);
    }
}