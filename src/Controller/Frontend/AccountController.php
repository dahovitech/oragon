<?php

namespace App\Controller\Frontend;

use App\Repository\OrderRepository;
use App\Repository\WishlistRepository;
use App\Repository\ReviewRepository;
use App\Service\LocaleService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/{_locale}', requirements: ['_locale' => 'fr|en|es|de|it'], defaults: ['_locale' => 'fr'])]
#[IsGranted('ROLE_USER')]
class AccountController extends AbstractController
{
    public function __construct(
        private OrderRepository $orderRepository,
        private WishlistRepository $wishlistRepository,
        private ReviewRepository $reviewRepository,
        private LocaleService $localeService,
        private EntityManagerInterface $entityManager,
        private PaginatorInterface $paginator
    ) {
    }

    #[Route('/account', name: 'account_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $user = $this->getUser();
        
        // Get user statistics
        $recentOrders = $this->orderRepository->findBy(
            ['user' => $user], 
            ['createdAt' => 'DESC'], 
            5
        );
        
        $wishlistCount = $this->wishlistRepository->count(['user' => $user]);
        $totalOrders = $this->orderRepository->count(['user' => $user]);
        $totalReviews = $this->reviewRepository->count(['user' => $user]);
        
        // Calculate total spent
        $totalSpent = $this->orderRepository->createQueryBuilder('o')
            ->select('SUM(o.totalAmount)')
            ->where('o.user = :user')
            ->andWhere('o.status IN (:completedStatuses)')
            ->setParameter('user', $user)
            ->setParameter('completedStatuses', ['delivered', 'completed'])
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return $this->render('account/dashboard.html.twig', [
            'recent_orders' => $recentOrders,
            'stats' => [
                'total_orders' => $totalOrders,
                'total_spent' => $totalSpent,
                'wishlist_count' => $wishlistCount,
                'reviews_count' => $totalReviews
            ],
            'default_locale' => $this->localeService->getDefaultLanguage()->getCode()
        ]);
    }

    #[Route('/account/profile', name: 'account_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request): Response
    {
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $user->setFirstName($request->request->get('first_name'));
            $user->setLastName($request->request->get('last_name'));
            $user->setPhone($request->request->get('phone'));
            
            // Handle date of birth
            $birthDate = $request->request->get('birth_date');
            if ($birthDate) {
                try {
                    $user->setBirthDate(new \DateTime($birthDate));
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Invalid birth date format');
                }
            }

            // Handle gender
            $gender = $request->request->get('gender');
            if (in_array($gender, ['male', 'female', 'other'])) {
                $user->setGender($gender);
            }

            // Handle preferences
            $preferences = $user->getPreferences() ?? [];
            $preferences['language'] = $request->request->get('preferred_language', $this->localeService->getCurrentLocale());
            $preferences['currency'] = $request->request->get('preferred_currency', 'EUR');
            $preferences['newsletter'] = $request->request->getBoolean('newsletter_subscription', false);
            $preferences['marketing'] = $request->request->getBoolean('marketing_emails', false);
            $user->setPreferences($preferences);

            $this->entityManager->flush();
            
            $this->addFlash('success', 'Profile updated successfully');
            return $this->redirectToRoute('account_profile', ['_locale' => $request->getLocale()]);
        }

        return $this->render('account/profile.html.twig', [
            'user' => $user,
            'available_languages' => $this->localeService->getActiveLanguages(),
            'default_locale' => $this->localeService->getDefaultLanguage()->getCode()
        ]);
    }

    #[Route('/account/orders', name: 'account_orders', methods: ['GET'])]
    public function orders(Request $request): Response
    {
        $user = $this->getUser();
        
        $queryBuilder = $this->orderRepository->createQueryBuilder('o')
            ->where('o.user = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC');

        // Filter by status
        $status = $request->query->get('status');
        if ($status && in_array($status, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])) {
            $queryBuilder->andWhere('o.status = :status')
                ->setParameter('status', $status);
        }

        // Filter by date range
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        
        if ($dateFrom) {
            $queryBuilder->andWhere('o.createdAt >= :dateFrom')
                ->setParameter('dateFrom', new \DateTime($dateFrom . ' 00:00:00'));
        }
        
        if ($dateTo) {
            $queryBuilder->andWhere('o.createdAt <= :dateTo')
                ->setParameter('dateTo', new \DateTime($dateTo . ' 23:59:59'));
        }

        $orders = $this->paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('account/orders.html.twig', [
            'orders' => $orders,
            'filters' => [
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ],
            'default_locale' => $this->localeService->getDefaultLanguage()->getCode()
        ]);
    }

    #[Route('/account/orders/{orderNumber}', name: 'account_order_detail', methods: ['GET'])]
    public function orderDetail(string $orderNumber): Response
    {
        $user = $this->getUser();
        
        $order = $this->orderRepository->findOneBy([
            'orderNumber' => $orderNumber,
            'user' => $user
        ]);

        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        return $this->render('account/order_detail.html.twig', [
            'order' => $order,
            'default_locale' => $this->localeService->getDefaultLanguage()->getCode()
        ]);
    }

    #[Route('/account/addresses', name: 'account_addresses', methods: ['GET'])]
    public function addresses(): Response
    {
        $user = $this->getUser();
        $addresses = $user->getAddresses();

        return $this->render('account/addresses.html.twig', [
            'addresses' => $addresses,
            'default_locale' => $this->localeService->getDefaultLanguage()->getCode()
        ]);
    }

    #[Route('/account/reviews', name: 'account_reviews', methods: ['GET'])]
    public function reviews(Request $request): Response
    {
        $user = $this->getUser();
        
        $queryBuilder = $this->reviewRepository->createQueryBuilder('r')
            ->leftJoin('r.product', 'p')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC');

        $reviews = $this->paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('account/reviews.html.twig', [
            'reviews' => $reviews,
            'default_locale' => $this->localeService->getDefaultLanguage()->getCode()
        ]);
    }

    #[Route('/account/settings', name: 'account_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            switch ($action) {
                case 'notifications':
                    $preferences = $user->getPreferences() ?? [];
                    $preferences['email_notifications'] = [
                        'order_updates' => $request->request->getBoolean('order_updates', false),
                        'promotions' => $request->request->getBoolean('promotions', false),
                        'newsletter' => $request->request->getBoolean('newsletter', false),
                        'product_reviews' => $request->request->getBoolean('product_reviews', false)
                    ];
                    $user->setPreferences($preferences);
                    $this->addFlash('success', 'Notification preferences updated');
                    break;

                case 'privacy':
                    $preferences = $user->getPreferences() ?? [];
                    $preferences['privacy'] = [
                        'profile_visibility' => $request->request->get('profile_visibility', 'private'),
                        'show_reviews' => $request->request->getBoolean('show_reviews', true),
                        'show_wishlist' => $request->request->getBoolean('show_wishlist', false)
                    ];
                    $user->setPreferences($preferences);
                    $this->addFlash('success', 'Privacy settings updated');
                    break;
            }

            $this->entityManager->flush();
            return $this->redirectToRoute('account_settings', ['_locale' => $request->getLocale()]);
        }

        return $this->render('account/settings.html.twig', [
            'user' => $user,
            'default_locale' => $this->localeService->getDefaultLanguage()->getCode()
        ]);
    }
}