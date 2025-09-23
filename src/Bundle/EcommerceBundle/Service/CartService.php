<?php

namespace App\Bundle\EcommerceBundle\Service;

use App\Bundle\EcommerceBundle\Entity\Cart;
use App\Bundle\EcommerceBundle\Entity\CartItem;
use App\Bundle\EcommerceBundle\Entity\Product;
use App\Bundle\EcommerceBundle\Entity\ProductVariant;
use App\Bundle\EcommerceBundle\Repository\CartRepository;
use App\Bundle\EcommerceBundle\Repository\CartItemRepository;
use App\Bundle\UserBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CartService
{
    private EntityManagerInterface $entityManager;
    private CartRepository $cartRepository;
    private CartItemRepository $cartItemRepository;
    private RequestStack $requestStack;

    public function __construct(
        EntityManagerInterface $entityManager,
        CartRepository $cartRepository,
        CartItemRepository $cartItemRepository,
        RequestStack $requestStack
    ) {
        $this->entityManager = $entityManager;
        $this->cartRepository = $cartRepository;
        $this->cartItemRepository = $cartItemRepository;
        $this->requestStack = $requestStack;
    }

    /**
     * Get or create cart for current session/user
     */
    public function getCurrentCart(?User $user = null): Cart
    {
        if ($user) {
            return $this->cartRepository->findOrCreateForUser($user);
        }

        $sessionId = $this->requestStack->getSession()->getId();
        return $this->cartRepository->findOrCreateForSession($sessionId);
    }

    /**
     * Add product to cart
     */
    public function addToCart(
        Product $product,
        int $quantity = 1,
        ?ProductVariant $variant = null,
        ?User $user = null,
        array $customOptions = []
    ): CartItem {
        $cart = $this->getCurrentCart($user);

        // Check if item already exists in cart
        $existingItem = $this->cartItemRepository->findByCartAndProduct($cart, $product, $variant);

        if ($existingItem) {
            // Update quantity of existing item
            $existingItem->setQuantity($existingItem->getQuantity() + $quantity);
            $existingItem->setCustomOptions(array_merge($existingItem->getCustomOptions() ?? [], $customOptions));
        } else {
            // Create new cart item
            $existingItem = new CartItem();
            $existingItem->setCart($cart);
            $existingItem->setProduct($product);
            $existingItem->setVariant($variant);
            $existingItem->setQuantity($quantity);
            $existingItem->setCustomOptions($customOptions);

            $cart->addItem($existingItem);
        }

        $this->updateCartTotals($cart);
        $this->entityManager->persist($existingItem);
        $this->entityManager->persist($cart);
        $this->entityManager->flush();

        return $existingItem;
    }

    /**
     * Update cart item quantity
     */
    public function updateCartItemQuantity(CartItem $cartItem, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeFromCart($cartItem);
            return;
        }

        $cartItem->setQuantity($quantity);
        $this->updateCartTotals($cartItem->getCart());

        $this->entityManager->persist($cartItem);
        $this->entityManager->flush();
    }

    /**
     * Remove item from cart
     */
    public function removeFromCart(CartItem $cartItem): void
    {
        $cart = $cartItem->getCart();
        $cart->removeItem($cartItem);

        $this->entityManager->remove($cartItem);
        $this->updateCartTotals($cart);
        $this->entityManager->persist($cart);
        $this->entityManager->flush();
    }

    /**
     * Clear cart
     */
    public function clearCart(Cart $cart): void
    {
        foreach ($cart->getItems() as $item) {
            $this->entityManager->remove($item);
        }

        $cart->clear();
        $this->entityManager->persist($cart);
        $this->entityManager->flush();
    }

    /**
     * Merge session cart with user cart on login
     */
    public function mergeSessionCartWithUser(User $user): Cart
    {
        $sessionId = $this->requestStack->getSession()->getId();
        return $this->cartRepository->mergeSessionCartWithUser($sessionId, $user);
    }

    /**
     * Apply coupon to cart
     */
    public function applyCoupon(Cart $cart, string $couponCode): bool
    {
        // TODO: Implement coupon validation logic
        // For now, just set the coupon code
        $cart->setCouponCode($couponCode);
        
        // Calculate discount based on coupon
        $discountAmount = $this->calculateCouponDiscount($cart, $couponCode);
        $cart->setDiscountAmount((string)$discountAmount);
        
        $this->updateCartTotals($cart);
        $this->entityManager->persist($cart);
        $this->entityManager->flush();

        return true; // Return false if coupon is invalid
    }

    /**
     * Remove coupon from cart
     */
    public function removeCoupon(Cart $cart): void
    {
        $cart->setCouponCode(null);
        $cart->setDiscountAmount('0.00');
        $this->updateCartTotals($cart);

        $this->entityManager->persist($cart);
        $this->entityManager->flush();
    }

    /**
     * Update cart totals
     */
    public function updateCartTotals(Cart $cart): void
    {
        $subtotal = 0;
        foreach ($cart->getItems() as $item) {
            $subtotal += (float)$item->getLineTotal();
        }

        $cart->setSubtotal((string)$subtotal);

        // Calculate tax (example: 20% VAT)
        $taxRate = 0.20;
        $taxAmount = $subtotal * $taxRate;
        $cart->setTaxAmount((string)$taxAmount);

        // Calculate shipping cost
        $shippingCost = $this->calculateShippingCost($cart);
        $cart->setShippingCost((string)$shippingCost);

        // Calculate total
        $total = $subtotal + $taxAmount + $shippingCost - (float)$cart->getDiscountAmount();
        $cart->setTotal((string)max(0, $total));
    }

    /**
     * Validate cart items stock
     */
    public function validateCartStock(Cart $cart): array
    {
        $errors = [];

        foreach ($cart->getItems() as $item) {
            if (!$item->hasEnoughStock()) {
                $errors[] = sprintf(
                    'Not enough stock for %s. Available: %d, Requested: %d',
                    $item->getDisplayName(),
                    $item->getEffectiveStock(),
                    $item->getQuantity()
                );
            }
        }

        return $errors;
    }

    /**
     * Calculate shipping cost
     */
    private function calculateShippingCost(Cart $cart): float
    {
        // Simple shipping calculation logic
        $subtotal = (float)$cart->getSubtotal();
        
        // Free shipping over 100€
        if ($subtotal >= 100) {
            return 0;
        }

        // Standard shipping rate
        return 9.99;
    }

    /**
     * Calculate coupon discount
     */
    private function calculateCouponDiscount(Cart $cart, string $couponCode): float
    {
        // Simple example discount logic
        $subtotal = (float)$cart->getSubtotal();

        switch (strtoupper($couponCode)) {
            case 'WELCOME10':
                return $subtotal * 0.10; // 10% discount
            case 'SAVE20':
                return min($subtotal * 0.20, 50); // 20% discount, max 50€
            case 'FREESHIP':
                return (float)$cart->getShippingCost(); // Free shipping
            default:
                return 0;
        }
    }

    /**
     * Get cart statistics
     */
    public function getCartStatistics(): array
    {
        return $this->cartRepository->getStatistics();
    }

    /**
     * Clean up old abandoned carts
     */
    public function cleanupAbandonedCarts(int $daysOld = 30): int
    {
        $cutoffDate = new \DateTimeImmutable("-{$daysOld} days");
        $abandonedCarts = $this->cartRepository->findAbandoned($cutoffDate);

        $cleaned = 0;
        foreach ($abandonedCarts as $cart) {
            if ($cart->isEmpty()) {
                $this->entityManager->remove($cart);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            $this->entityManager->flush();
        }

        return $cleaned;
    }
}