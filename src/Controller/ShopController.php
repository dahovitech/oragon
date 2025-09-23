<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ShopController extends AbstractController
{
    #[Route('/shop', name: 'app_shop_index')]
    public function index(ProductRepository $productRepository, CategoryRepository $categoryRepository): Response
    {
        $products = $productRepository->findBy(['isActive' => true], ['createdAt' => 'DESC']);
        $categories = $categoryRepository->findBy(['isActive' => true], ['position' => 'ASC']);

        return $this->render('shop/index.html.twig', [
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    #[Route('/shop/category/{slug}', name: 'app_shop_category')]
    public function category(string $slug, CategoryRepository $categoryRepository, ProductRepository $productRepository): Response
    {
        $category = $categoryRepository->findOneBy(['slug' => $slug, 'isActive' => true]);
        
        if (!$category) {
            throw $this->createNotFoundException('Category not found');
        }

        $products = $productRepository->findBy(['category' => $category, 'isActive' => true]);

        return $this->render('shop/category.html.twig', [
            'category' => $category,
            'products' => $products,
        ]);
    }

    #[Route('/shop/product/{slug}', name: 'app_shop_product')]
    public function product(string $slug, ProductRepository $productRepository): Response
    {
        $product = $productRepository->findOneBy(['slug' => $slug, 'isActive' => true]);
        
        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        $relatedProducts = $productRepository->findBy(
            ['category' => $product->getCategory(), 'isActive' => true],
            ['createdAt' => 'DESC'],
            4
        );

        return $this->render('shop/product.html.twig', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
        ]);
    }
}
