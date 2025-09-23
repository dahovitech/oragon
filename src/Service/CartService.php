<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Service for managing shopping cart and orders
 */
class CartService
{
    private const CART_SESSION_KEY = 'shopping_cart';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack
    ) {
    }

    /**
     * Add product to cart
     */
    public function addToCart(Product $product, int $quantity = 1, array $attributes = []): void
    {
        if (!$product->isActive() || !$product->isInStock()) {
            throw new \InvalidArgumentException('Product is not available');
        }

        if ($product->isTrackStock() && $quantity > $product->getStockQuantity()) {
            throw new \InvalidArgumentException('Not enough stock available');
        }

        $cart = $this->getCart();
        $productKey = $this->generateProductKey($product->getId(), $attributes);

        if (isset($cart['items'][$productKey])) {
            $cart['items'][$productKey]['quantity'] += $quantity;
        } else {
            $cart['items'][$productKey] = [
                'product_id' => $product->getId(),
                'quantity' => $quantity,
                'attributes' => $attributes,
                'added_at' => time()
            ];
        }

        $this->saveCart($cart);
    }

    /**
     * Update product quantity in cart
     */
    public function updateQuantity(string $productKey, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeFromCart($productKey);
            return;
        }

        $cart = $this->getCart();
        
        if (isset($cart['items'][$productKey])) {
            $productId = $cart['items'][$productKey]['product_id'];
            $product = $this->entityManager->getRepository(Product::class)->find($productId);
            
            if ($product && $product->isTrackStock() && $quantity > $product->getStockQuantity()) {
                throw new \InvalidArgumentException('Not enough stock available');
            }

            $cart['items'][$productKey]['quantity'] = $quantity;
            $this->saveCart($cart);
        }
    }

    /**
     * Remove product from cart
     */
    public function removeFromCart(string $productKey): void
    {
        $cart = $this->getCart();
        
        if (isset($cart['items'][$productKey])) {
            unset($cart['items'][$productKey]);
            $this->saveCart($cart);
        }
    }

    /**
     * Clear entire cart
     */
    public function clearCart(): void
    {
        $this->getSession()->remove(self::CART_SESSION_KEY);
    }

    /**
     * Get cart contents
     */
    public function getCart(): array
    {
        $cart = $this->getSession()->get(self::CART_SESSION_KEY, ['items' => []]);
        
        // Ensure cart structure
        if (!isset($cart['items'])) {
            $cart['items'] = [];
        }

        return $cart;
    }

    /**
     * Get cart items with product details
     */
    public function getCartItems(string $locale = 'fr'): array
    {
        $cart = $this->getCart();
        $items = [];

        foreach ($cart['items'] as $key => $item) {
            $product = $this->entityManager->getRepository(Product::class)->find($item['product_id']);
            
            if (!$product || !$product->isActive()) {
                // Remove invalid items
                $this->removeFromCart($key);
                continue;
            }

            $items[] = [
                'key' => $key,
                'product' => $product,
                'quantity' => $item['quantity'],
                'attributes' => $item['attributes'] ?? [],
                'unit_price' => (float) $product->getPrice(),
                'total_price' => (float) $product->getPrice() * $item['quantity'],
                'name' => $product->getName($locale),
                'image' => $product->getMainImage(),
                'added_at' => $item['added_at'] ?? time()
            ];
        }

        return $items;
    }

    /**
     * Get cart summary
     */
    public function getCartSummary(string $locale = 'fr'): array
    {
        $items = $this->getCartItems($locale);
        $subtotal = 0;
        $totalItems = 0;

        foreach ($items as $item) {
            $subtotal += $item['total_price'];
            $totalItems += $item['quantity'];
        }

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'total_items' => $totalItems,
            'item_count' => count($items),
            'shipping' => 0, // Will be calculated based on shipping method
            'tax' => 0, // Will be calculated based on location
            'total' => $subtotal // Will include shipping and tax
        ];
    }

    /**
     * Check if cart is empty
     */
    public function isEmpty(): bool
    {
        $cart = $this->getCart();
        return empty($cart['items']);
    }

    /**
     * Get cart items count
     */
    public function getItemsCount(): int
    {
        $cart = $this->getCart();
        $count = 0;
        
        foreach ($cart['items'] as $item) {
            $count += $item['quantity'];
        }
        
        return $count;
    }

    /**
     * Create order from cart
     */
    public function createOrderFromCart(?User $user, array $shippingAddress, array $billingAddress, string $locale = 'fr'): Order
    {
        if ($this->isEmpty()) {
            throw new \InvalidArgumentException('Cannot create order from empty cart');
        }

        $items = $this->getCartItems($locale);
        $summary = $this->getCartSummary($locale);

        $order = new Order();
        $order->setUser($user);
        $order->setShippingAddress($shippingAddress);
        $order->setBillingAddress($billingAddress);
        $order->setTotalAmount(number_format($summary['total'], 2, '.', ''));

        // Create order items
        foreach ($items as $item) {
            $orderItem = new OrderItem();
            $orderItem->setOrder($order);
            $orderItem->setProduct($item['product']);
            $orderItem->setQuantity($item['quantity']);
            $orderItem->setUnitPrice(number_format($item['unit_price'], 2, '.', ''));
            $orderItem->setTotalPrice(number_format($item['total_price'], 2, '.', ''));
            $orderItem->setProductName($item['name']);
            $orderItem->setProductSku($item['product']->getSku());
            
            if (!empty($item['attributes'])) {
                $orderItem->setProductAttributes($item['attributes']);
            }

            if ($item['image']) {
                $orderItem->setProductImageUrl($item['image']->getUrl());
            }

            $order->addItem($orderItem);
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        // Clear cart after successful order creation
        $this->clearCart();

        return $order;
    }

    /**
     * Apply coupon to cart
     */
    public function applyCoupon(string $couponCode): bool
    {
        // TODO: Implement coupon logic
        $cart = $this->getCart();
        $cart['coupon'] = $couponCode;
        $this->saveCart($cart);
        
        return true;
    }

    /**
     * Remove coupon from cart
     */
    public function removeCoupon(): void
    {
        $cart = $this->getCart();
        unset($cart['coupon']);
        $this->saveCart($cart);
    }

    /**
     * Validate cart before checkout
     */
    public function validateCart(): array
    {
        $errors = [];
        $items = $this->getCartItems();

        foreach ($items as $item) {
            $product = $item['product'];
            
            if (!$product->isActive()) {
                $errors[] = sprintf('Product "%s" is no longer available', $item['name']);
                continue;
            }

            if (!$product->isInStock()) {
                $errors[] = sprintf('Product "%s" is out of stock', $item['name']);
                continue;
            }

            if ($product->isTrackStock() && $item['quantity'] > $product->getStockQuantity()) {
                $errors[] = sprintf(
                    'Only %d items of "%s" are available (you have %d in cart)',
                    $product->getStockQuantity(),
                    $item['name'],
                    $item['quantity']
                );
            }
        }

        return $errors;
    }

    /**
     * Generate unique key for product with attributes
     */
    private function generateProductKey(int $productId, array $attributes = []): string
    {
        $key = 'product_' . $productId;
        
        if (!empty($attributes)) {
            ksort($attributes);
            $key .= '_' . md5(serialize($attributes));
        }
        
        return $key;
    }

    /**
     * Save cart to session
     */
    private function saveCart(array $cart): void
    {
        $this->getSession()->set(self::CART_SESSION_KEY, $cart);
    }

    /**
     * Get session
     */
    private function getSession(): SessionInterface
    {
        return $this->requestStack->getSession();
    }
}