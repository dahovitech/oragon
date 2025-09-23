<?php

namespace App\Bundle\EcommerceBundle\Service;

use App\Bundle\EcommerceBundle\Entity\Cart;
use App\Bundle\EcommerceBundle\Entity\Order;
use App\Bundle\EcommerceBundle\Entity\OrderItem;
use App\Bundle\EcommerceBundle\Repository\OrderRepository;
use App\Bundle\UserBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class OrderService
{
    private EntityManagerInterface $entityManager;
    private OrderRepository $orderRepository;
    private MailerInterface $mailer;
    private Environment $twig;
    private UrlGeneratorInterface $urlGenerator;
    private string $fromEmail;

    public function __construct(
        EntityManagerInterface $entityManager,
        OrderRepository $orderRepository,
        MailerInterface $mailer,
        Environment $twig,
        UrlGeneratorInterface $urlGenerator,
        string $fromEmail = 'noreply@example.com'
    ) {
        $this->entityManager = $entityManager;
        $this->orderRepository = $orderRepository;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->urlGenerator = $urlGenerator;
        $this->fromEmail = $fromEmail;
    }

    /**
     * Create order from cart
     */
    public function createOrderFromCart(
        Cart $cart,
        User $user,
        array $billingAddress,
        array $shippingAddress = null,
        string $paymentMethod = 'card',
        string $shippingMethod = 'standard'
    ): Order {
        if ($cart->isEmpty()) {
            throw new \InvalidArgumentException('Cannot create order from empty cart');
        }

        $order = new Order();
        $order->setUser($user);
        $order->setStatus(Order::STATUS_PENDING);
        $order->setPaymentStatus(Order::PAYMENT_STATUS_PENDING);

        // Set financial data from cart
        $order->setSubtotal($cart->getSubtotal());
        $order->setTaxAmount($cart->getTaxAmount());
        $order->setShippingCost($cart->getShippingCost());
        $order->setDiscountAmount($cart->getDiscountAmount());
        $order->setTotal($cart->getTotal());
        $order->setCouponCode($cart->getCouponCode());

        // Set billing address
        $this->setBillingAddress($order, $billingAddress);

        // Set shipping address (use billing if not provided)
        $this->setShippingAddress($order, $shippingAddress ?? $billingAddress);

        // Set payment and shipping methods
        $order->setPaymentMethod($paymentMethod);
        $order->setShippingMethod($shippingMethod);

        // Create order items from cart items
        foreach ($cart->getItems() as $cartItem) {
            $orderItem = OrderItem::createFromCartItem($cartItem);
            $order->addItem($orderItem);
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        // Send order confirmation email
        $this->sendOrderConfirmationEmail($order);

        return $order;
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(Order $order, string $status, bool $sendEmail = true): void
    {
        $previousStatus = $order->getStatus();
        $order->setStatus($status);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        if ($sendEmail && $status !== $previousStatus) {
            $this->sendOrderStatusUpdateEmail($order, $previousStatus);
        }
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus(Order $order, string $paymentStatus, ?string $transactionId = null): void
    {
        $order->setPaymentStatus($paymentStatus);
        
        if ($transactionId) {
            $order->setPaymentTransactionId($transactionId);
        }

        // Auto-confirm order when payment is successful
        if ($paymentStatus === Order::PAYMENT_STATUS_PAID && $order->getStatus() === Order::STATUS_PENDING) {
            $order->setStatus(Order::STATUS_CONFIRMED);
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();
    }

    /**
     * Cancel order
     */
    public function cancelOrder(Order $order, string $reason = null): void
    {
        if (!$order->isCancellable()) {
            throw new \InvalidArgumentException('Order cannot be cancelled in current status');
        }

        $order->setStatus(Order::STATUS_CANCELLED);
        
        if ($reason) {
            $notes = $order->getNotes() ? $order->getNotes() . "\n" : '';
            $order->setNotes($notes . "Cancelled: " . $reason);
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        // Send cancellation email
        $this->sendOrderCancellationEmail($order, $reason);
    }

    /**
     * Add tracking number to order
     */
    public function addTrackingNumber(Order $order, string $trackingNumber): void
    {
        $order->setTrackingNumber($trackingNumber);
        
        // Auto-update status to shipped if still processing
        if ($order->getStatus() === Order::STATUS_PROCESSING) {
            $order->setStatus(Order::STATUS_SHIPPED);
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        // Send shipping notification email
        $this->sendShippingNotificationEmail($order);
    }

    /**
     * Process refund
     */
    public function processRefund(Order $order, float $amount = null): void
    {
        $totalAmount = (float)$order->getTotal();
        $refundAmount = $amount ?? $totalAmount;

        if ($refundAmount > $totalAmount) {
            throw new \InvalidArgumentException('Refund amount cannot exceed order total');
        }

        // Update payment status
        if ($refundAmount >= $totalAmount) {
            $order->setPaymentStatus(Order::PAYMENT_STATUS_REFUNDED);
            $order->setStatus(Order::STATUS_REFUNDED);
        } else {
            $order->setPaymentStatus(Order::PAYMENT_STATUS_PARTIAL_REFUND);
        }

        // Add refund note
        $notes = $order->getNotes() ? $order->getNotes() . "\n" : '';
        $order->setNotes($notes . "Refunded: €" . number_format($refundAmount, 2));

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        // Send refund confirmation email
        $this->sendRefundConfirmationEmail($order, $refundAmount);
    }

    /**
     * Get order statistics
     */
    public function getOrderStatistics(\DateTimeInterface $from = null, \DateTimeInterface $to = null): array
    {
        return $this->orderRepository->getStatistics($from, $to);
    }

    /**
     * Get monthly revenue
     */
    public function getMonthlyRevenue(int $year): array
    {
        return $this->orderRepository->getMonthlyRevenue($year);
    }

    private function setBillingAddress(Order $order, array $address): void
    {
        $order->setBillingFirstName($address['firstName']);
        $order->setBillingLastName($address['lastName']);
        $order->setBillingEmail($address['email']);
        $order->setBillingPhone($address['phone'] ?? null);
        $order->setBillingAddress($address['address']);
        $order->setBillingAddress2($address['address2'] ?? null);
        $order->setBillingCity($address['city']);
        $order->setBillingState($address['state'] ?? null);
        $order->setBillingPostalCode($address['postalCode']);
        $order->setBillingCountry($address['country']);
    }

    private function setShippingAddress(Order $order, array $address): void
    {
        $order->setShippingFirstName($address['firstName']);
        $order->setShippingLastName($address['lastName']);
        $order->setShippingPhone($address['phone'] ?? null);
        $order->setShippingAddress($address['address']);
        $order->setShippingAddress2($address['address2'] ?? null);
        $order->setShippingCity($address['city']);
        $order->setShippingState($address['state'] ?? null);
        $order->setShippingPostalCode($address['postalCode']);
        $order->setShippingCountry($address['country']);
    }

    private function sendOrderConfirmationEmail(Order $order): void
    {
        try {
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($order->getBillingEmail())
                ->subject('Order Confirmation #' . $order->getOrderNumber())
                ->html($this->twig->render('emails/order_confirmation.html.twig', [
                    'order' => $order,
                    'orderUrl' => $this->urlGenerator->generate('ecommerce_order_view', [
                        'orderNumber' => $order->getOrderNumber()
                    ], UrlGeneratorInterface::ABSOLUTE_URL)
                ]));

            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log error but don't fail the order creation
        }
    }

    private function sendOrderStatusUpdateEmail(Order $order, string $previousStatus): void
    {
        try {
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($order->getBillingEmail())
                ->subject('Order Update #' . $order->getOrderNumber())
                ->html($this->twig->render('emails/order_status_update.html.twig', [
                    'order' => $order,
                    'previousStatus' => $previousStatus,
                    'orderUrl' => $this->urlGenerator->generate('ecommerce_order_view', [
                        'orderNumber' => $order->getOrderNumber()
                    ], UrlGeneratorInterface::ABSOLUTE_URL)
                ]));

            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log error but don't fail the status update
        }
    }

    private function sendShippingNotificationEmail(Order $order): void
    {
        try {
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($order->getBillingEmail())
                ->subject('Your Order Has Shipped #' . $order->getOrderNumber())
                ->html($this->twig->render('emails/shipping_notification.html.twig', [
                    'order' => $order,
                    'trackingUrl' => $this->generateTrackingUrl($order->getTrackingNumber())
                ]));

            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log error
        }
    }

    private function sendOrderCancellationEmail(Order $order, ?string $reason): void
    {
        try {
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($order->getBillingEmail())
                ->subject('Order Cancelled #' . $order->getOrderNumber())
                ->html($this->twig->render('emails/order_cancellation.html.twig', [
                    'order' => $order,
                    'reason' => $reason
                ]));

            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log error
        }
    }

    private function sendRefundConfirmationEmail(Order $order, float $refundAmount): void
    {
        try {
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($order->getBillingEmail())
                ->subject('Refund Processed #' . $order->getOrderNumber())
                ->html($this->twig->render('emails/refund_confirmation.html.twig', [
                    'order' => $order,
                    'refundAmount' => $refundAmount
                ]));

            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log error
        }
    }

    private function generateTrackingUrl(?string $trackingNumber): ?string
    {
        if (!$trackingNumber) {
            return null;
        }

        // Example tracking URL - customize based on shipping provider
        return "https://www.laposte.fr/outils/suivre-vos-envois?code=" . urlencode($trackingNumber);
    }
}