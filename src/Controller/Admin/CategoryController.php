<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Repository\LanguageRepository;
use App\Service\EcommerceTranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/category', name: 'admin_category_')]
#[IsGranted('ROLE_ADMIN')]
class CategoryController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categoryRepository,
        private LanguageRepository $languageRepository,
        private EcommerceTranslationService $translationService
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $categories = $this->categoryRepository->findCategoriesTree();
        $languages = $this->languageRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);

        return $this->render('admin/category/index.html.twig', [
            'categories' => $categories,
            'languages' => $languages
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $category = new Category();
        $languages = $this->languageRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);
        $parentCategories = $this->categoryRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);

        if ($request->isMethod('POST')) {
            return $this->handleCategorySubmission($request, $category, $languages);
        }

        return $this->render('admin/category/new.html.twig', [
            'category' => $category,
            'languages' => $languages,
            'parent_categories' => $parentCategories
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Category $category): Response
    {
        $languages = $this->languageRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);
        $parentCategories = $this->categoryRepository->findBy([
            'isActive' => true
        ], ['sortOrder' => 'ASC']);
        
        // Remove current category and its descendants from parent options
        $parentCategories = array_filter($parentCategories, function($parent) use ($category) {
            return $parent->getId() !== $category->getId() && 
                   !in_array($category, $parent->getAllDescendants());
        });

        if ($request->isMethod('POST')) {
            return $this->handleCategorySubmission($request, $category, $languages);
        }

        return $this->render('admin/category/edit.html.twig', [
            'category' => $category,
            'languages' => $languages,
            'parent_categories' => $parentCategories
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Category $category): Response
    {
        $languages = $this->languageRepository->findBy(['isActive' => true], ['sortOrder' => 'ASC']);
        $products = $category->getProducts();

        return $this->render('admin/category/show.html.twig', [
            'category' => $category,
            'languages' => $languages,
            'products' => $products
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST', 'DELETE'])]
    public function delete(Request $request, Category $category): Response
    {
        if ($this->isCsrfTokenValid('delete' . $category->getId(), $request->request->get('_token'))) {
            // Check if category has children
            if (!$category->getChildren()->isEmpty()) {
                $this->addFlash('error', 'Cannot delete category with subcategories');
                return $this->redirectToRoute('admin_category_index');
            }

            // Check if category has products
            if (!$category->getProducts()->isEmpty()) {
                $this->addFlash('error', 'Cannot delete category with products');
                return $this->redirectToRoute('admin_category_index');
            }

            $this->entityManager->remove($category);
            $this->entityManager->flush();

            $this->addFlash('success', 'Category deleted successfully');
        } else {
            $this->addFlash('error', 'Invalid token');
        }

        return $this->redirectToRoute('admin_category_index');
    }

    #[Route('/reorder', name: 'reorder', methods: ['POST'])]
    public function reorder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['categories'])) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid data']);
        }

        foreach ($data['categories'] as $item) {
            $category = $this->categoryRepository->find($item['id']);
            if ($category) {
                $category->setSortOrder($item['position']);
                
                // Handle parent change
                if (isset($item['parent_id'])) {
                    $parent = $this->categoryRepository->find($item['parent_id']);
                    $category->setParent($parent);
                } else {
                    $category->setParent(null);
                }
            }
        }

        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id}/duplicate-translation', name: 'duplicate_translation', methods: ['POST'])]
    public function duplicateTranslation(Request $request, Category $category): JsonResponse
    {
        $sourceLanguage = $request->request->get('source_language');
        $targetLanguage = $request->request->get('target_language');

        if (!$sourceLanguage || !$targetLanguage) {
            return new JsonResponse(['success' => false, 'message' => 'Missing parameters']);
        }

        $success = $this->translationService->duplicateTranslation(
            Category::class,
            $category->getId(),
            $sourceLanguage,
            $targetLanguage
        );

        if ($success) {
            return new JsonResponse(['success' => true, 'message' => 'Translation duplicated successfully']);
        } else {
            return new JsonResponse(['success' => false, 'message' => 'Failed to duplicate translation']);
        }
    }

    #[Route('/bulk-action', name: 'bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request): Response
    {
        $action = $request->request->get('action');
        $categoryIds = $request->request->get('categories', []);

        if (empty($categoryIds)) {
            $this->addFlash('warning', 'No categories selected');
            return $this->redirectToRoute('admin_category_index');
        }

        $categories = $this->categoryRepository->findBy(['id' => $categoryIds]);
        $count = 0;

        switch ($action) {
            case 'activate':
                foreach ($categories as $category) {
                    $category->setIsActive(true);
                    $count++;
                }
                $this->addFlash('success', sprintf('%d categories activated', $count));
                break;

            case 'deactivate':
                foreach ($categories as $category) {
                    $category->setIsActive(false);
                    $count++;
                }
                $this->addFlash('success', sprintf('%d categories deactivated', $count));
                break;

            case 'delete':
                foreach ($categories as $category) {
                    // Check constraints before deletion
                    if ($category->getChildren()->isEmpty() && $category->getProducts()->isEmpty()) {
                        $this->entityManager->remove($category);
                        $count++;
                    }
                }
                $this->addFlash('success', sprintf('%d categories deleted', $count));
                break;

            default:
                $this->addFlash('error', 'Unknown action');
                return $this->redirectToRoute('admin_category_index');
        }

        $this->entityManager->flush();
        return $this->redirectToRoute('admin_category_index');
    }

    private function handleCategorySubmission(Request $request, Category $category, array $languages): Response
    {
        $data = $request->request->all();

        // Validate required fields
        $errors = $this->validateCategoryData($data);
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('admin_category_edit', ['id' => $category->getId()]);
        }

        // Set basic category data
        $category->setIsActive(isset($data['is_active']));
        $category->setSortOrder((int) ($data['sort_order'] ?? 0));
        $category->setImageUrl($data['image_url'] ?? null);

        // Set parent category
        if (!empty($data['parent_id'])) {
            $parent = $this->categoryRepository->find($data['parent_id']);
            if ($parent) {
                // Prevent circular reference
                if ($parent === $category || in_array($category, $parent->getAllDescendants())) {
                    $this->addFlash('error', 'Cannot set category as its own descendant');
                    return $this->redirectToRoute('admin_category_edit', ['id' => $category->getId()]);
                }
                $category->setParent($parent);
            }
        } else {
            $category->setParent(null);
        }

        // Handle translations
        $translationsData = [];
        foreach ($languages as $language) {
            $langCode = $language->getCode();
            if (isset($data['translations'][$langCode])) {
                $translationsData[$langCode] = $data['translations'][$langCode];
            }
        }

        // Save category with translations
        $this->translationService->createOrUpdateCategory($category, $translationsData);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        $this->addFlash('success', 'Category saved successfully');

        return $this->redirectToRoute('admin_category_edit', ['id' => $category->getId()]);
    }

    private function validateCategoryData(array $data): array
    {
        $errors = [];

        // Validate at least one translation
        $hasTranslation = false;
        if (isset($data['translations'])) {
            foreach ($data['translations'] as $translation) {
                if (!empty($translation['name'])) {
                    $hasTranslation = true;
                    break;
                }
            }
        }

        if (!$hasTranslation) {
            $errors[] = 'At least one translation name is required';
        }

        // Validate sort order
        if (isset($data['sort_order']) && !is_numeric($data['sort_order'])) {
            $errors[] = 'Sort order must be a number';
        }

        return $errors;
    }
}