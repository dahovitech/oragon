<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\User;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\ShippingMethod;
use App\Entity\PaymentMethod;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CartService
{
    private const CART_SESSION_KEY = 'shopping_cart';

    public function __construct(
        private RequestStack $requestStack,
        private ProductRepository $productRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Add product to cart
     */
    public function addProduct(int $productId, int $quantity = 1, array $options = []): bool
    {
        $product = $this->productRepository->find($productId);
        
        if (!$product || !$product->isActive()) {
            return false;
        }

        $cart = $this->getCart();
        $itemKey = $this->generateItemKey($productId, $options);

        if (isset($cart['items'][$itemKey])) {
            $cart['items'][$itemKey]['quantity'] += $quantity;
        } else {
            $cart['items'][$itemKey] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'options' => $options,
                'added_at' => time()
            ];
        }

        $this->saveCart($cart);
        return true;
    }

    /**
     * Update item quantity in cart
     */
    public function updateQuantity(string $itemKey, int $quantity): bool
    {
        $cart = $this->getCart();

        if (!isset($cart['items'][$itemKey])) {
            return false;
        }

        if ($quantity <= 0) {
            return $this->removeItem($itemKey);
        }

        $cart['items'][$itemKey]['quantity'] = $quantity;
        $this->saveCart($cart);
        return true;
    }

    /**
     * Remove item from cart
     */
    public function removeItem(string $itemKey): bool
    {
        $cart = $this->getCart();

        if (!isset($cart['items'][$itemKey])) {
            return false;
        }

        unset($cart['items'][$itemKey]);
        $this->saveCart($cart);
        return true;
    }

    /**
     * Clear entire cart
     */
    public function clear(): void
    {
        $this->saveCart(['items' => []]);
    }

    /**
     * Get cart with product details
     */
    public function getCartWithDetails(): array
    {
        $cart = $this->getCart();
        $cartWithDetails = [
            'items' => [],
            'totals' => []
        ];

        foreach ($cart['items'] as $itemKey => $item) {
            $product = $this->productRepository->find($item['product_id']);
            
            if ($product && $product->isActive()) {
                $cartWithDetails['items'][$itemKey] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'options' => $item['options'],
                    'unit_price' => $product->getPrice(),
                    'total_price' => $product->getPrice() * $item['quantity'],
                    'added_at' => $item['added_at']
                ];
            }
        }

        $cartWithDetails['totals'] = $this->calculateTotals($cartWithDetails['items']);
        return $cartWithDetails;
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
     * Check if cart is empty
     */
    public function isEmpty(): bool
    {
        $cart = $this->getCart();
        return empty($cart['items']);
    }

    /**
     * Calculate cart totals
     */
    public function calculateTotals(array $cartItems): array
    {
        $subtotal = 0;
        $totalWeight = 0;
        $totalItems = 0;

        foreach ($cartItems as $item) {
            $subtotal += $item['total_price'];
            $totalWeight += ($item['product']->getWeight() ?? 0) * $item['quantity'];
            $totalItems += $item['quantity'];
        }

        return [
            'subtotal' => $subtotal,
            'total_weight' => $totalWeight,
            'total_items' => $totalItems,
            'shipping' => 0, // Will be calculated when shipping method is selected
            'tax' => 0, // Will be calculated based on location
            'discount' => 0, // Will be calculated if coupon is applied
            'total' => $subtotal
        ];
    }

    /**
     * Apply shipping method to cart
     */
    public function applyShipping(ShippingMethod $shippingMethod): array
    {
        $cart = $this->getCartWithDetails();
        $totals = $cart['totals'];
        
        $shippingCost = $shippingMethod->calculateCost($totals['subtotal'], $totals['total_weight']);
        
        if ($shippingCost >= 0) {
            $totals['shipping'] = $shippingCost;
            $totals['total'] = $totals['subtotal'] + $totals['shipping'] + $totals['tax'] - $totals['discount'];
        }

        return $totals;
    }

    /**
     * Create order from cart
     */
    public function createOrder(?User $user = null, array $shippingAddress = [], array $billingAddress = []): Order
    {
        $cart = $this->getCartWithDetails();
        
        if (empty($cart['items'])) {
            throw new \InvalidArgumentException('Cannot create order from empty cart');
        }

        $order = new Order();
        
        if ($user) {
            $order->setUser($user);
        }

        $order->setShippingAddress($shippingAddress);
        $order->setBillingAddress($billingAddress);

        // Add items to order
        foreach ($cart['items'] as $item) {
            $orderItem = new OrderItem();
            $orderItem->setProduct($item['product']);
            $orderItem->setQuantity($item['quantity']);
            $orderItem->setUnitPrice((string) $item['unit_price']);
            $orderItem->setTotalPrice((string) $item['total_price']);
            $orderItem->setProductName($item['product']->getName()); // Store name at time of order
            
            $order->addItem($orderItem);
        }

        // Calculate totals
        $totals = $cart['totals'];
        $order->setTotalAmount((string) $totals['total']);
        $order->setShippingAmount((string) $totals['shipping']);
        $order->setTaxAmount((string) $totals['tax']);
        $order->setDiscountAmount((string) $totals['discount']);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        // Clear cart after order creation
        $this->clear();

        return $order;
    }

    /**
     * Restore cart from order (for re-ordering)
     */
    public function restoreFromOrder(Order $order): void
    {
        $this->clear();

        foreach ($order->getItems() as $orderItem) {
            if ($orderItem->getProduct() && $orderItem->getProduct()->isActive()) {
                $this->addProduct(
                    $orderItem->getProduct()->getId(),
                    $orderItem->getQuantity()
                );
            }
        }
    }

    /**
     * Check if product is in cart
     */
    public function hasProduct(int $productId, array $options = []): bool
    {
        $cart = $this->getCart();
        $itemKey = $this->generateItemKey($productId, $options);
        
        return isset($cart['items'][$itemKey]);
    }

    /**
     * Get product quantity in cart
     */
    public function getProductQuantity(int $productId, array $options = []): int
    {
        $cart = $this->getCart();
        $itemKey = $this->generateItemKey($productId, $options);
        
        return $cart['items'][$itemKey]['quantity'] ?? 0;
    }

    /**
     * Validate cart (check product availability, prices, etc.)
     */
    public function validateCart(): array
    {
        $cart = $this->getCart();
        $errors = [];
        $hasChanges = false;

        foreach ($cart['items'] as $itemKey => $item) {
            $product = $this->productRepository->find($item['product_id']);
            
            if (!$product) {
                $errors[] = "Product no longer exists and was removed from cart";
                unset($cart['items'][$itemKey]);
                $hasChanges = true;
                continue;
            }

            if (!$product->isActive()) {
                $errors[] = "Product '{$product->getName()}' is no longer available and was removed from cart";
                unset($cart['items'][$itemKey]);
                $hasChanges = true;
                continue;
            }

            // Check stock if available
            if (method_exists($product, 'getStockQuantity')) {
                $stockQuantity = $product->getStockQuantity();
                if ($stockQuantity !== null && $item['quantity'] > $stockQuantity) {
                    $cart['items'][$itemKey]['quantity'] = $stockQuantity;
                    $errors[] = "Quantity for '{$product->getName()}' was reduced to available stock ({$stockQuantity})";
                    $hasChanges = true;
                }
            }
        }

        if ($hasChanges) {
            $this->saveCart($cart);
        }

        return $errors;
    }

    /**
     * Get raw cart data from session
     */
    private function getCart(): array
    {
        $session = $this->getSession();
        return $session->get(self::CART_SESSION_KEY, ['items' => []]);
    }

    /**
     * Save cart to session
     */
    private function saveCart(array $cart): void
    {
        $session = $this->getSession();
        $session->set(self::CART_SESSION_KEY, $cart);
    }

    /**
     * Generate unique key for cart item
     */
    private function generateItemKey(int $productId, array $options = []): string
    {
        $optionsString = empty($options) ? '' : '_' . md5(json_encode($options));
        return 'product_' . $productId . $optionsString;
    }

    /**
     * Get session
     */
    private function getSession(): SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request || !$request->hasSession()) {
            throw new \RuntimeException('No session available');
        }

        return $request->getSession();
    }
}