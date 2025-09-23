<?php

namespace App\Bundle\ApiBundle\Controller;

use App\Bundle\EcommerceBundle\Entity\Product;
use App\Bundle\EcommerceBundle\Entity\Category as EcommerceCategory;
use App\Bundle\EcommerceBundle\Entity\Order;
use App\Bundle\EcommerceBundle\Entity\OrderItem;
use App\Bundle\EcommerceBundle\Repository\ProductRepository;
use App\Bundle\EcommerceBundle\Repository\CategoryRepository as EcommerceCategoryRepository;
use App\Bundle\EcommerceBundle\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;

#[Route('/api/ecommerce', name: 'api_ecommerce_')]
class EcommerceApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository,
        private EcommerceCategoryRepository $categoryRepository,
        private OrderRepository $orderRepository,
        private ValidatorInterface $validator
    ) {}

    #[Route('/products', name: 'products_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/ecommerce/products',
        summary: 'Get list of products',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', description: 'Page number', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', description: 'Items per page', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'category', in: 'query', description: 'Filter by category ID', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'active', in: 'query', description: 'Filter by active status', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Search in name and description', schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of products',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'products', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'pagination', type: 'object')
                    ]
                )
            )
        ]
    )]
    public function listProducts(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(50, max(1, $request->query->getInt('limit', 10)));
        $categoryId = $request->query->get('category');
        $active = $request->query->get('active');
        $search = $request->query->get('search');

        $criteria = [];
        if ($active !== null) {
            $criteria['isActive'] = filter_var($active, FILTER_VALIDATE_BOOLEAN);
        }

        $products = $this->productRepository->findByCriteria($criteria, $page, $limit, $categoryId, $search);
        $total = $this->productRepository->countByCriteria($criteria, $categoryId, $search);

        $productsData = array_map(function (Product $product) {
            return [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'slug' => $product->getSlug(),
                'description' => $product->getDescription(),
                'shortDescription' => $product->getShortDescription(),
                'price' => $product->getPrice(),
                'salePrice' => $product->getSalePrice(),
                'sku' => $product->getSku(),
                'stock' => $product->getStock(),
                'isActive' => $product->isActive(),
                'featuredImage' => $product->getFeaturedImage(),
                'images' => $product->getImages(),
                'weight' => $product->getWeight(),
                'dimensions' => $product->getDimensions(),
                'createdAt' => $product->getCreatedAt()->format('c'),
                'updatedAt' => $product->getUpdatedAt()->format('c'),
                'category' => $product->getCategory() ? [
                    'id' => $product->getCategory()->getId(),
                    'name' => $product->getCategory()->getName(),
                    'slug' => $product->getCategory()->getSlug(),
                ] : null,
            ];
        }, $products);

        return new JsonResponse([
            'products' => $productsData,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    #[Route('/products/{id}', name: 'product_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/api/ecommerce/products/{id}',
        summary: 'Get a specific product',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'Product ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product details'),
            new OA\Response(response: 404, description: 'Product not found')
        ]
    )]
    public function showProduct(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'slug' => $product->getSlug(),
            'description' => $product->getDescription(),
            'shortDescription' => $product->getShortDescription(),
            'price' => $product->getPrice(),
            'salePrice' => $product->getSalePrice(),
            'sku' => $product->getSku(),
            'stock' => $product->getStock(),
            'isActive' => $product->isActive(),
            'featuredImage' => $product->getFeaturedImage(),
            'images' => $product->getImages(),
            'weight' => $product->getWeight(),
            'dimensions' => $product->getDimensions(),
            'metaTitle' => $product->getMetaTitle(),
            'metaDescription' => $product->getMetaDescription(),
            'createdAt' => $product->getCreatedAt()->format('c'),
            'updatedAt' => $product->getUpdatedAt()->format('c'),
            'category' => $product->getCategory() ? [
                'id' => $product->getCategory()->getId(),
                'name' => $product->getCategory()->getName(),
                'slug' => $product->getCategory()->getSlug(),
                'description' => $product->getCategory()->getDescription(),
            ] : null,
        ]);
    }

    #[Route('/products', name: 'product_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(
        path: '/api/ecommerce/products',
        summary: 'Create a new product',
        security: [['bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'shortDescription', type: 'string'),
                    new OA\Property(property: 'price', type: 'number'),
                    new OA\Property(property: 'salePrice', type: 'number'),
                    new OA\Property(property: 'sku', type: 'string'),
                    new OA\Property(property: 'stock', type: 'integer'),
                    new OA\Property(property: 'categoryId', type: 'integer'),
                    new OA\Property(property: 'isActive', type: 'boolean')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Product created'),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function createProduct(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $product = new Product();
        $product->setName($data['name'] ?? '');
        $product->setDescription($data['description'] ?? '');
        $product->setShortDescription($data['shortDescription'] ?? '');
        $product->setPrice($data['price'] ?? 0);
        $product->setSalePrice($data['salePrice'] ?? null);
        $product->setSku($data['sku'] ?? '');
        $product->setStock($data['stock'] ?? 0);
        $product->setIsActive($data['isActive'] ?? true);

        if (isset($data['categoryId'])) {
            $category = $this->categoryRepository->find($data['categoryId']);
            if ($category) {
                $product->setCategory($category);
            }
        }

        $errors = $this->validator->validate($product);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Product created successfully',
            'product' => [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'slug' => $product->getSlug(),
                'sku' => $product->getSku(),
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/categories', name: 'categories_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/ecommerce/categories',
        summary: 'Get list of product categories',
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of categories',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'categories', type: 'array', items: new OA\Items(type: 'object'))
                    ]
                )
            )
        ]
    )]
    public function listCategories(): JsonResponse
    {
        $categories = $this->categoryRepository->findAll();

        $categoriesData = array_map(function (EcommerceCategory $category) {
            return [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
                'description' => $category->getDescription(),
                'image' => $category->getImage(),
                'isActive' => $category->isActive(),
                'productsCount' => $category->getProducts()->count(),
            ];
        }, $categories);

        return new JsonResponse(['categories' => $categoriesData]);
    }

    #[Route('/orders', name: 'orders_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/ecommerce/orders',
        summary: 'Get user orders',
        security: [['bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', description: 'Page number', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', description: 'Items per page', schema: new OA\Schema(type: 'integer', default: 10))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of orders',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'orders', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'pagination', type: 'object')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function listOrders(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(50, max(1, $request->query->getInt('limit', 10)));

        $user = $this->getUser();
        $orders = $this->orderRepository->findByUser($user, $page, $limit);
        $total = $this->orderRepository->countByUser($user);

        $ordersData = array_map(function (Order $order) {
            return [
                'id' => $order->getId(),
                'orderNumber' => $order->getOrderNumber(),
                'status' => $order->getStatus(),
                'totalAmount' => $order->getTotalAmount(),
                'createdAt' => $order->getCreatedAt()->format('c'),
                'updatedAt' => $order->getUpdatedAt()->format('c'),
                'itemsCount' => $order->getOrderItems()->count(),
                'shippingAddress' => $order->getShippingAddress(),
                'billingAddress' => $order->getBillingAddress(),
            ];
        }, $orders);

        return new JsonResponse([
            'orders' => $ordersData,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    #[Route('/orders/{id}', name: 'order_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/ecommerce/orders/{id}',
        summary: 'Get a specific order',
        security: [['bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'Order ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Order details'),
            new OA\Response(response: 404, description: 'Order not found'),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function showOrder(int $id): JsonResponse
    {
        $order = $this->orderRepository->find($id);

        if (!$order) {
            return new JsonResponse(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if user owns this order or is admin
        if ($order->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $orderItems = array_map(function (OrderItem $item) {
            return [
                'id' => $item->getId(),
                'product' => [
                    'id' => $item->getProduct()->getId(),
                    'name' => $item->getProduct()->getName(),
                    'sku' => $item->getProduct()->getSku(),
                ],
                'quantity' => $item->getQuantity(),
                'unitPrice' => $item->getUnitPrice(),
                'totalPrice' => $item->getTotalPrice(),
            ];
        }, $order->getOrderItems()->toArray());

        return new JsonResponse([
            'id' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'status' => $order->getStatus(),
            'totalAmount' => $order->getTotalAmount(),
            'createdAt' => $order->getCreatedAt()->format('c'),
            'updatedAt' => $order->getUpdatedAt()->format('c'),
            'shippingAddress' => $order->getShippingAddress(),
            'billingAddress' => $order->getBillingAddress(),
            'paymentMethod' => $order->getPaymentMethod(),
            'paymentStatus' => $order->getPaymentStatus(),
            'notes' => $order->getNotes(),
            'items' => $orderItems,
            'user' => [
                'id' => $order->getUser()->getId(),
                'firstName' => $order->getUser()->getFirstName(),
                'lastName' => $order->getUser()->getLastName(),
                'email' => $order->getUser()->getEmail(),
            ]
        ]);
    }

    #[Route('/orders', name: 'order_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        path: '/api/ecommerce/orders',
        summary: 'Create a new order',
        security: [['bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'shippingAddress', type: 'object'),
                    new OA\Property(property: 'billingAddress', type: 'object'),
                    new OA\Property(property: 'paymentMethod', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Order created'),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function createOrder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['items']) || empty($data['items'])) {
            return new JsonResponse(['error' => 'Order items are required'], Response::HTTP_BAD_REQUEST);
        }

        $order = new Order();
        $order->setUser($this->getUser());
        $order->setShippingAddress($data['shippingAddress'] ?? []);
        $order->setBillingAddress($data['billingAddress'] ?? []);
        $order->setPaymentMethod($data['paymentMethod'] ?? 'credit_card');
        $order->setStatus('pending');
        $order->setPaymentStatus('pending');

        $totalAmount = 0;

        foreach ($data['items'] as $itemData) {
            $product = $this->productRepository->find($itemData['productId']);
            if (!$product) {
                return new JsonResponse(['error' => 'Product not found: ' . $itemData['productId']], Response::HTTP_BAD_REQUEST);
            }

            $quantity = max(1, (int)$itemData['quantity']);
            $unitPrice = $product->getSalePrice() ?? $product->getPrice();

            $orderItem = new OrderItem();
            $orderItem->setOrder($order);
            $orderItem->setProduct($product);
            $orderItem->setQuantity($quantity);
            $orderItem->setUnitPrice($unitPrice);
            $orderItem->setTotalPrice($unitPrice * $quantity);

            $order->addOrderItem($orderItem);
            $totalAmount += $orderItem->getTotalPrice();
        }

        $order->setTotalAmount($totalAmount);

        $errors = $this->validator->validate($order);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Order created successfully',
            'order' => [
                'id' => $order->getId(),
                'orderNumber' => $order->getOrderNumber(),
                'totalAmount' => $order->getTotalAmount(),
                'status' => $order->getStatus(),
            ]
        ], Response::HTTP_CREATED);
    }
}