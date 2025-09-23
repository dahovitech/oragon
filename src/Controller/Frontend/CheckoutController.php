<?php

namespace App\Controller\Frontend;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Entity\Address;
use App\Repository\OrderRepository;
use App\Repository\PaymentMethodRepository;
use App\Repository\ShippingMethodRepository;
use App\Service\CartService;
use App\Service\LocaleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/{_locale}', requirements: ['_locale' => 'fr|en|es|de|it'], defaults: ['_locale' => 'fr'])]
class CheckoutController extends AbstractController
{
    public function __construct(
        private CartService $cartService,
        private LocaleService $localeService,
        private EntityManagerInterface $entityManager,
        private PaymentMethodRepository $paymentMethodRepository,
        private ShippingMethodRepository $shippingMethodRepository,
        private OrderRepository $orderRepository
    ) {
    }

    #[Route('/checkout', name: 'checkout_index', methods: ['GET'])]
    public function index(): Response
    {
        $cartItems = $this->cartService->getItems();
        
        if (empty($cartItems)) {
            $this->addFlash('warning', 'Your cart is empty');
            return $this->redirectToRoute('cart_index', ['_locale' => $this->localeService->getCurrentLocale()]);
        }

        $cartTotal = $this->cartService->getTotal();
        $cartSubtotal = $this->cartService->getSubtotal();

        // Get available payment and shipping methods
        $paymentMethods = $this->paymentMethodRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);
        $shippingMethods = $this->shippingMethodRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);

        return $this->render('checkout/index.html.twig', [
            'cart_items' => $cartItems,
            'cart_total' => $cartTotal,
            'cart_subtotal' => $cartSubtotal,
            'payment_methods' => $paymentMethods,
            'shipping_methods' => $shippingMethods,
            'default_locale' => $this->localeService->getDefaultLanguage()->getCode()
        ]);
    }

    #[Route('/checkout/process', name: 'checkout_process', methods: ['POST'])]
    public function process(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $cartItems = $this->cartService->getItems();
        
        if (empty($cartItems)) {
            return new JsonResponse(['success' => false, 'message' => 'Your cart is empty'], 400);
        }

        $billingData = $request->request->all('billing');
        $shippingData = $request->request->all('shipping');
        $createAccount = $request->request->getBoolean('create_account');
        $differentShipping = $request->request->getBoolean('different_shipping');
        $paymentMethodId = $request->request->get('payment_method');
        $shippingMethodId = $request->request->get('shipping_method');
        $orderNotes = $request->request->get('order_notes', '');
        
        // Validation
        if (!$this->validateCheckoutData($billingData, $shippingData, $differentShipping)) {
            return new JsonResponse(['success' => false, 'message' => 'Please fill in all required fields'], 400);
        }

        $user = $this->getUser();
        
        // Create account if requested and user is not logged in
        if (!$user && $createAccount) {
            $password = $request->request->get('password');
            $passwordConfirm = $request->request->get('password_confirm');
            
            if (!$password || $password !== $passwordConfirm) {
                return new JsonResponse(['success' => false, 'message' => 'Password confirmation does not match'], 400);
            }

            // Check if email already exists
            $existingUser = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $billingData['email']]);
                
            if ($existingUser) {
                return new JsonResponse(['success' => false, 'message' => 'An account with this email already exists'], 400);
            }

            // Create new user
            $user = new User();
            $user->setEmail($billingData['email']);
            $user->setFirstName($billingData['first_name']);
            $user->setLastName($billingData['last_name']);
            $user->setPhone($billingData['phone'] ?? null);
            $user->setPassword($passwordHasher->hashPassword($user, $password));
            $user->setRoles(['ROLE_USER']);
            $user->setIsActive(true);
            $user->setCreatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($user);
        }

        // Create order
        $order = new Order();
        $order->setOrderNumber($this->generateOrderNumber());
        $order->setUser($user);
        
        // Set billing address
        $billingAddress = [
            'first_name' => $billingData['first_name'],
            'last_name' => $billingData['last_name'],
            'email' => $billingData['email'],
            'phone' => $billingData['phone'] ?? '',
            'address1' => $billingData['address1'],
            'address2' => $billingData['address2'] ?? '',
            'city' => $billingData['city'],
            'postal_code' => $billingData['postal_code'],
            'country' => $billingData['country']
        ];
        $order->setBillingAddress($billingAddress);

        // Set shipping address
        if ($differentShipping && !empty($shippingData)) {
            $shippingAddress = [
                'first_name' => $shippingData['first_name'],
                'last_name' => $shippingData['last_name'],
                'email' => $shippingData['email'] ?? $billingData['email'],
                'phone' => $shippingData['phone'] ?? $billingData['phone'],
                'address1' => $shippingData['address1'],
                'address2' => $shippingData['address2'] ?? '',
                'city' => $shippingData['city'],
                'postal_code' => $shippingData['postal_code'],
                'country' => $shippingData['country']
            ];
            $order->setShippingAddress($shippingAddress);
        } else {
            $order->setShippingAddress($billingAddress);
        }

        // Set payment and shipping methods
        if ($paymentMethodId) {
            $paymentMethod = $this->paymentMethodRepository->find($paymentMethodId);
            $order->setPaymentMethod($paymentMethod);
        }

        if ($shippingMethodId) {
            $shippingMethod = $this->shippingMethodRepository->find($shippingMethodId);
            $order->setShippingMethod($shippingMethod);
            $order->setShippingAmount($shippingMethod->getPrice());
        }

        $order->setNotes($orderNotes);
        $order->setStatus('pending');
        $order->setPaymentStatus('pending');
        $order->setCreatedAt(new \DateTimeImmutable());

        // Add order items
        $totalAmount = 0;
        foreach ($cartItems as $cartItem) {
            $orderItem = new OrderItem();
            $orderItem->setOrder($order);
            $orderItem->setProduct($cartItem['product']);
            $orderItem->setQuantity($cartItem['quantity']);
            $orderItem->setUnitPrice($cartItem['product']->getPrice());
            $orderItem->setTotalPrice($cartItem['quantity'] * $cartItem['product']->getPrice());
            $orderItem->setProductName($cartItem['product']->getTranslation($this->localeService->getCurrentLocale())->getName());

            $this->entityManager->persist($orderItem);
            $totalAmount += $orderItem->getTotalPrice();
        }

        $order->setTotalAmount($totalAmount + ($order->getShippingAmount() ?? 0));
        
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        // Clear cart
        $this->cartService->clear();

        return new JsonResponse([
            'success' => true,
            'order_id' => $order->getId(),
            'redirect_url' => $this->generateUrl('checkout_success', [
                'orderNumber' => $order->getOrderNumber(),
                '_locale' => $this->localeService->getCurrentLocale()
            ])
        ]);
    }

    #[Route('/checkout/success/{orderNumber}', name: 'checkout_success', methods: ['GET'])]
    public function success(string $orderNumber): Response
    {
        $order = $this->orderRepository->findOneBy(['orderNumber' => $orderNumber]);
        
        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        // Check if user has permission to view this order
        $user = $this->getUser();
        if (!$user || $order->getUser() !== $user) {
            if (!$order->getUserEmail() || $order->getUserEmail() !== ($user ? $user->getEmail() : null)) {
                throw $this->createAccessDeniedException();
            }
        }

        return $this->render('checkout/success.html.twig', [
            'order' => $order,
            'default_locale' => $this->localeService->getDefaultLanguage()->getCode()
        ]);
    }

    #[Route('/api/checkout/calculate-shipping', name: 'checkout_calculate_shipping', methods: ['POST'])]
    public function calculateShipping(Request $request): JsonResponse
    {
        $country = $request->request->get('country');
        $postalCode = $request->request->get('postal_code');

        // Simple tax calculation based on country
        $taxRate = match ($country) {
            'FR' => 0.20, // 20% VAT in France
            'DE' => 0.19, // 19% VAT in Germany
            'ES' => 0.21, // 21% VAT in Spain
            'IT' => 0.22, // 22% VAT in Italy
            'GB' => 0.20, // 20% VAT in UK
            default => 0.0 // No tax for other countries
        };

        return new JsonResponse([
            'success' => true,
            'tax_rate' => $taxRate,
            'country' => $country,
            'postal_code' => $postalCode
        ]);
    }

    private function validateCheckoutData(array $billingData, array $shippingData, bool $differentShipping): bool
    {
        $requiredBilling = ['first_name', 'last_name', 'email', 'address1', 'city', 'postal_code', 'country'];
        
        foreach ($requiredBilling as $field) {
            if (empty($billingData[$field])) {
                return false;
            }
        }

        if ($differentShipping) {
            $requiredShipping = ['first_name', 'last_name', 'address1', 'city', 'postal_code', 'country'];
            
            foreach ($requiredShipping as $field) {
                if (empty($shippingData[$field])) {
                    return false;
                }
            }
        }

        // Validate email
        if (!filter_var($billingData['email'], FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return true;
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-' . date('Y') . '-' . strtoupper(uniqid());
    }
}