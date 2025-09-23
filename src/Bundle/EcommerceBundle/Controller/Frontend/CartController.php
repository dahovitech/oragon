<?php

namespace App\Bundle\EcommerceBundle\Controller\Frontend;

use App\Bundle\EcommerceBundle\Entity\Product;
use App\Bundle\EcommerceBundle\Entity\ProductVariant;
use App\Bundle\EcommerceBundle\Entity\CartItem;
use App\Bundle\EcommerceBundle\Service\CartService;
use App\Bundle\EcommerceBundle\Repository\CartItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/cart', name: 'ecommerce_cart_')]
class CartController extends AbstractController
{
    private CartService $cartService;
    private CartItemRepository $cartItemRepository;

    public function __construct(CartService $cartService, CartItemRepository $cartItemRepository)
    {
        $this->cartService = $cartService;
        $this->cartItemRepository = $cartItemRepository;
    }

    #[Route('/', name: 'show', methods: ['GET'])]
    public function show(): Response
    {
        $cart = $this->cartService->getCurrentCart($this->getUser());
        
        // Validate stock for all items
        $stockErrors = $this->cartService->validateCartStock($cart);

        return $this->render('@Ecommerce/frontend/cart/show.html.twig', [
            'cart' => $cart,
            'stock_errors' => $stockErrors,
        ]);
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        $productId = $request->request->getInt('product_id');
        $variantId = $request->request->getInt('variant_id') ?: null;
        $quantity = max(1, $request->request->getInt('quantity', 1));
        $customOptions = $request->request->all('custom_options') ?? [];

        try {
            $product = $this->getDoctrine()->getRepository(Product::class)->find($productId);
            if (!$product || !$product->isActive()) {
                return $this->json(['success' => false, 'message' => 'Product not found or inactive'], 404);
            }

            $variant = null;
            if ($variantId) {
                $variant = $this->getDoctrine()->getRepository(ProductVariant::class)->find($variantId);
                if (!$variant || !$variant->isActive()) {
                    return $this->json(['success' => false, 'message' => 'Product variant not found or inactive'], 404);
                }
            }

            // Check stock availability
            $availableStock = $variant ? $variant->getStock() : $product->getStock();
            $trackStock = $variant ? true : $product->isTrackStock();

            if ($trackStock && $availableStock < $quantity) {
                return $this->json([
                    'success' => false, 
                    'message' => 'Not enough stock available. Available: ' . $availableStock
                ], 400);
            }

            $cartItem = $this->cartService->addToCart($product, $quantity, $variant, $this->getUser(), $customOptions);
            $cart = $cartItem->getCart();

            return $this->json([
                'success' => true,
                'message' => 'Product added to cart',
                'cart' => [
                    'items_count' => $cart->getItemsCount(),
                    'total_quantity' => $cart->getTotalQuantity(),
                    'subtotal' => $cart->getSubtotal(),
                    'total' => $cart->getTotal(),
                ],
                'item' => [
                    'id' => $cartItem->getId(),
                    'name' => $cartItem->getDisplayName(),
                    'quantity' => $cartItem->getQuantity(),
                    'unit_price' => $cartItem->getUnitPrice(),
                    'line_total' => $cartItem->getLineTotal(),
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Error adding product to cart'], 500);
        }
    }

    #[Route('/update/{id}', name: 'update_item', methods: ['POST'])]
    public function updateItem(CartItem $cartItem, Request $request): JsonResponse
    {
        if ($cartItem->getCart()->getUser() !== $this->getUser() && 
            $cartItem->getCart()->getSessionId() !== $request->getSession()->getId()) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $quantity = max(1, $request->request->getInt('quantity', 1));

        try {
            // Check stock availability
            if (!$cartItem->hasEnoughStock()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Not enough stock available. Available: ' . $cartItem->getEffectiveStock()
                ], 400);
            }

            $this->cartService->updateCartItemQuantity($cartItem, $quantity);
            $cart = $cartItem->getCart();

            return $this->json([
                'success' => true,
                'message' => 'Cart updated',
                'cart' => [
                    'items_count' => $cart->getItemsCount(),
                    'total_quantity' => $cart->getTotalQuantity(),
                    'subtotal' => $cart->getSubtotal(),
                    'total' => $cart->getTotal(),
                ],
                'item' => [
                    'quantity' => $cartItem->getQuantity(),
                    'line_total' => $cartItem->getLineTotal(),
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Error updating cart'], 500);
        }
    }

    #[Route('/remove/{id}', name: 'remove_item', methods: ['POST', 'DELETE'])]
    public function removeItem(CartItem $cartItem, Request $request): JsonResponse
    {
        if ($cartItem->getCart()->getUser() !== $this->getUser() && 
            $cartItem->getCart()->getSessionId() !== $request->getSession()->getId()) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $cart = $cartItem->getCart();
            $this->cartService->removeFromCart($cartItem);

            return $this->json([
                'success' => true,
                'message' => 'Item removed from cart',
                'cart' => [
                    'items_count' => $cart->getItemsCount(),
                    'total_quantity' => $cart->getTotalQuantity(),
                    'subtotal' => $cart->getSubtotal(),
                    'total' => $cart->getTotal(),
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Error removing item'], 500);
        }
    }

    #[Route('/clear', name: 'clear', methods: ['POST'])]
    public function clear(): JsonResponse
    {
        try {
            $cart = $this->cartService->getCurrentCart($this->getUser());
            $this->cartService->clearCart($cart);

            return $this->json([
                'success' => true,
                'message' => 'Cart cleared',
                'cart' => [
                    'items_count' => 0,
                    'total_quantity' => 0,
                    'subtotal' => '0.00',
                    'total' => '0.00',
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Error clearing cart'], 500);
        }
    }

    #[Route('/coupon/apply', name: 'apply_coupon', methods: ['POST'])]
    public function applyCoupon(Request $request): JsonResponse
    {
        $couponCode = trim($request->request->get('coupon_code', ''));

        if (empty($couponCode)) {
            return $this->json(['success' => false, 'message' => 'Please enter a coupon code'], 400);
        }

        try {
            $cart = $this->cartService->getCurrentCart($this->getUser());
            $success = $this->cartService->applyCoupon($cart, $couponCode);

            if ($success) {
                return $this->json([
                    'success' => true,
                    'message' => 'Coupon applied successfully',
                    'cart' => [
                        'coupon_code' => $cart->getCouponCode(),
                        'discount_amount' => $cart->getDiscountAmount(),
                        'subtotal' => $cart->getSubtotal(),
                        'total' => $cart->getTotal(),
                    ]
                ]);
            } else {
                return $this->json(['success' => false, 'message' => 'Invalid coupon code'], 400);
            }

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Error applying coupon'], 500);
        }
    }

    #[Route('/coupon/remove', name: 'remove_coupon', methods: ['POST'])]
    public function removeCoupon(): JsonResponse
    {
        try {
            $cart = $this->cartService->getCurrentCart($this->getUser());
            $this->cartService->removeCoupon($cart);

            return $this->json([
                'success' => true,
                'message' => 'Coupon removed',
                'cart' => [
                    'coupon_code' => null,
                    'discount_amount' => '0.00',
                    'subtotal' => $cart->getSubtotal(),
                    'total' => $cart->getTotal(),
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Error removing coupon'], 500);
        }
    }

    #[Route('/count', name: 'count', methods: ['GET'])]
    public function count(): JsonResponse
    {
        try {
            $cart = $this->cartService->getCurrentCart($this->getUser());

            return $this->json([
                'items_count' => $cart->getItemsCount(),
                'total_quantity' => $cart->getTotalQuantity(),
            ]);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Error getting cart count'], 500);
        }
    }

    #[Route('/mini', name: 'mini', methods: ['GET'])]
    public function mini(): Response
    {
        $cart = $this->cartService->getCurrentCart($this->getUser());

        return $this->render('@Ecommerce/frontend/cart/mini.html.twig', [
            'cart' => $cart,
        ]);
    }
}