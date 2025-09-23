<?php

namespace App\Bundle\ApiBundle\Controller;

use App\Bundle\BlogBundle\Entity\Post;
use App\Bundle\BlogBundle\Entity\Category;
use App\Bundle\BlogBundle\Repository\PostRepository;
use App\Bundle\BlogBundle\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;

#[Route('/api/blog', name: 'api_blog_')]
class BlogApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PostRepository $postRepository,
        private CategoryRepository $categoryRepository,
        private ValidatorInterface $validator
    ) {}

    #[Route('/posts', name: 'posts_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/blog/posts',
        summary: 'Get list of blog posts',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', description: 'Page number', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', description: 'Items per page', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'category', in: 'query', description: 'Filter by category ID', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'published', in: 'query', description: 'Filter by published status', schema: new OA\Schema(type: 'boolean'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of blog posts',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'posts', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'pagination', type: 'object')
                    ]
                )
            )
        ]
    )]
    public function listPosts(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(50, max(1, $request->query->getInt('limit', 10)));
        $categoryId = $request->query->get('category');
        $published = $request->query->get('published');

        $criteria = [];
        if ($published !== null) {
            $criteria['isPublished'] = filter_var($published, FILTER_VALIDATE_BOOLEAN);
        }

        $posts = $this->postRepository->findByCriteria($criteria, $page, $limit, $categoryId);
        $total = $this->postRepository->countByCriteria($criteria, $categoryId);

        $postsData = array_map(function (Post $post) {
            return [
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'slug' => $post->getSlug(),
                'excerpt' => $post->getExcerpt(),
                'content' => $post->getContent(),
                'featuredImage' => $post->getFeaturedImage(),
                'isPublished' => $post->isPublished(),
                'publishedAt' => $post->getPublishedAt()?->format('c'),
                'createdAt' => $post->getCreatedAt()->format('c'),
                'updatedAt' => $post->getUpdatedAt()->format('c'),
                'author' => [
                    'id' => $post->getAuthor()->getId(),
                    'firstName' => $post->getAuthor()->getFirstName(),
                    'lastName' => $post->getAuthor()->getLastName(),
                ],
                'category' => $post->getCategory() ? [
                    'id' => $post->getCategory()->getId(),
                    'name' => $post->getCategory()->getName(),
                    'slug' => $post->getCategory()->getSlug(),
                ] : null,
            ];
        }, $posts);

        return new JsonResponse([
            'posts' => $postsData,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    #[Route('/posts/{id}', name: 'post_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/api/blog/posts/{id}',
        summary: 'Get a specific blog post',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'Post ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Blog post details'),
            new OA\Response(response: 404, description: 'Post not found')
        ]
    )]
    public function showPost(int $id): JsonResponse
    {
        $post = $this->postRepository->find($id);

        if (!$post) {
            return new JsonResponse(['error' => 'Post not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $post->getId(),
            'title' => $post->getTitle(),
            'slug' => $post->getSlug(),
            'excerpt' => $post->getExcerpt(),
            'content' => $post->getContent(),
            'featuredImage' => $post->getFeaturedImage(),
            'isPublished' => $post->isPublished(),
            'publishedAt' => $post->getPublishedAt()?->format('c'),
            'createdAt' => $post->getCreatedAt()->format('c'),
            'updatedAt' => $post->getUpdatedAt()->format('c'),
            'author' => [
                'id' => $post->getAuthor()->getId(),
                'firstName' => $post->getAuthor()->getFirstName(),
                'lastName' => $post->getAuthor()->getLastName(),
            ],
            'category' => $post->getCategory() ? [
                'id' => $post->getCategory()->getId(),
                'name' => $post->getCategory()->getName(),
                'slug' => $post->getCategory()->getSlug(),
            ] : null,
        ]);
    }

    #[Route('/posts', name: 'post_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(
        path: '/api/blog/posts',
        summary: 'Create a new blog post',
        security: [['bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'content', type: 'string'),
                    new OA\Property(property: 'excerpt', type: 'string'),
                    new OA\Property(property: 'categoryId', type: 'integer'),
                    new OA\Property(property: 'featuredImage', type: 'string'),
                    new OA\Property(property: 'isPublished', type: 'boolean')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Post created'),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function createPost(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $post = new Post();
        $post->setTitle($data['title'] ?? '');
        $post->setContent($data['content'] ?? '');
        $post->setExcerpt($data['excerpt'] ?? '');
        $post->setAuthor($this->getUser());
        $post->setFeaturedImage($data['featuredImage'] ?? null);
        $post->setIsPublished($data['isPublished'] ?? false);

        if (isset($data['categoryId'])) {
            $category = $this->categoryRepository->find($data['categoryId']);
            if ($category) {
                $post->setCategory($category);
            }
        }

        $errors = $this->validator->validate($post);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Post created successfully',
            'post' => [
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'slug' => $post->getSlug(),
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/posts/{id}', name: 'post_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Put(
        path: '/api/blog/posts/{id}',
        summary: 'Update a blog post',
        security: [['bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'Post ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Post updated'),
            new OA\Response(response: 404, description: 'Post not found'),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function updatePost(int $id, Request $request): JsonResponse
    {
        $post = $this->postRepository->find($id);

        if (!$post) {
            return new JsonResponse(['error' => 'Post not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['title'])) {
            $post->setTitle($data['title']);
        }
        if (isset($data['content'])) {
            $post->setContent($data['content']);
        }
        if (isset($data['excerpt'])) {
            $post->setExcerpt($data['excerpt']);
        }
        if (isset($data['featuredImage'])) {
            $post->setFeaturedImage($data['featuredImage']);
        }
        if (isset($data['isPublished'])) {
            $post->setIsPublished($data['isPublished']);
        }

        if (isset($data['categoryId'])) {
            $category = $this->categoryRepository->find($data['categoryId']);
            $post->setCategory($category);
        }

        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Post updated successfully']);
    }

    #[Route('/posts/{id}', name: 'post_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Delete(
        path: '/api/blog/posts/{id}',
        summary: 'Delete a blog post',
        security: [['bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'Post ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Post deleted'),
            new OA\Response(response: 404, description: 'Post not found'),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function deletePost(int $id): JsonResponse
    {
        $post = $this->postRepository->find($id);

        if (!$post) {
            return new JsonResponse(['error' => 'Post not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($post);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/categories', name: 'categories_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/blog/categories',
        summary: 'Get list of blog categories',
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

        $categoriesData = array_map(function (Category $category) {
            return [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
                'description' => $category->getDescription(),
                'postsCount' => $category->getPosts()->count(),
            ];
        }, $categories);

        return new JsonResponse(['categories' => $categoriesData]);
    }

    #[Route('/categories', name: 'category_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(
        path: '/api/blog/categories',
        summary: 'Create a new blog category',
        security: [['bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Category created'),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function createCategory(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $category = new Category();
        $category->setName($data['name'] ?? '');
        $category->setDescription($data['description'] ?? '');

        $errors = $this->validator->validate($category);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Category created successfully',
            'category' => [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
            ]
        ], Response::HTTP_CREATED);
    }
}