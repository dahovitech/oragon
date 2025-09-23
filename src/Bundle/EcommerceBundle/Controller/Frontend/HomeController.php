<?php

namespace App\Bundle\EcommerceBundle\Controller\Frontend;

use App\Bundle\EcommerceBundle\Service\ProductService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    private ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    #[Route('/', name: 'ecommerce_homepage', methods: ['GET'])]
    public function index(): Response
    {
        // Get featured products for homepage
        $featuredProducts = $this->productService->getFeaturedProducts(8);
        
        // Get best selling products
        $bestSellingProducts = $this->productService->getBestSellingProducts(4);
        
        // Get latest products
        $latestProducts = $this->productService->searchProducts('', [
            'sort' => 'created',
            'order' => 'DESC'
        ], 4, 0);

        return $this->render('ecommerce_home.html.twig', [
            'featured_products' => $featuredProducts,
            'best_selling_products' => $bestSellingProducts,
            'latest_products' => $latestProducts,
        ]);
    }
}