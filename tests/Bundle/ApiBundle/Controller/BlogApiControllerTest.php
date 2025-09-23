<?php

namespace App\Tests\Bundle\ApiBundle\Controller;

use App\Bundle\BlogBundle\Entity\Post;
use App\Bundle\BlogBundle\Entity\Category;
use App\Bundle\UserBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class BlogApiControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private string $adminToken;
    private User $adminUser;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        // Start a database transaction
        $this->entityManager->beginTransaction();

        // Create admin user and get token
        $this->createAdminUser();
    }

    protected function tearDown(): void
    {
        // Roll back the transaction to clean up the database
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }

        parent::tearDown();
    }

    private function createAdminUser(): void
    {
        $this->adminUser = new User();
        $this->adminUser->setEmail('admin@example.com');
        $this->adminUser->setFirstName('Admin');
        $this->adminUser->setLastName('User');
        $this->adminUser->setRoles(['ROLE_ADMIN']);
        $hashedPassword = $this->passwordHasher->hashPassword($this->adminUser, 'adminpassword');
        $this->adminUser->setPassword($hashedPassword);

        $this->entityManager->persist($this->adminUser);
        $this->entityManager->flush();

        // Get JWT token
        $this->client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'admin@example.com',
            'password' => 'adminpassword'
        ]));

        $loginResponse = json_decode($this->client->getResponse()->getContent(), true);
        $this->adminToken = $loginResponse['token'];
    }

    public function testListPosts(): void
    {
        // Create test data
        $category = new Category();
        $category->setName('Test Category');
        $this->entityManager->persist($category);

        $post1 = new Post();
        $post1->setTitle('Test Post 1');
        $post1->setContent('Content of test post 1');
        $post1->setAuthor($this->adminUser);
        $post1->setCategory($category);
        $post1->setIsPublished(true);
        $this->entityManager->persist($post1);

        $post2 = new Post();
        $post2->setTitle('Test Post 2');
        $post2->setContent('Content of test post 2');
        $post2->setAuthor($this->adminUser);
        $post2->setIsPublished(false);
        $this->entityManager->persist($post2);

        $this->entityManager->flush();

        $this->client->request('GET', '/api/blog/posts');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('posts', $response);
        $this->assertArrayHasKey('pagination', $response);
        $this->assertIsArray($response['posts']);
    }

    public function testListPostsWithFilters(): void
    {
        $this->client->request('GET', '/api/blog/posts?page=1&limit=5&published=true');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('posts', $response);
        $this->assertArrayHasKey('pagination', $response);
        $this->assertEquals(1, $response['pagination']['page']);
        $this->assertEquals(5, $response['pagination']['limit']);
    }

    public function testShowPost(): void
    {
        // Create test post
        $post = new Post();
        $post->setTitle('Show Test Post');
        $post->setContent('Content of show test post');
        $post->setAuthor($this->adminUser);
        $post->setIsPublished(true);
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/blog/posts/' . $post->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Show Test Post', $response['title']);
        $this->assertEquals('Content of show test post', $response['content']);
    }

    public function testShowNonExistentPost(): void
    {
        $this->client->request('GET', '/api/blog/posts/99999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Post not found', $response['error']);
    }

    public function testCreatePost(): void
    {
        $category = new Category();
        $category->setName('Create Test Category');
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        $this->client->request('POST', '/api/blog/posts', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'title' => 'New Test Post',
            'content' => 'Content of new test post',
            'excerpt' => 'Excerpt of new test post',
            'categoryId' => $category->getId(),
            'isPublished' => true
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
        $this->assertArrayHasKey('post', $response);
        $this->assertEquals('Post created successfully', $response['message']);
        $this->assertEquals('New Test Post', $response['post']['title']);
    }

    public function testCreatePostWithoutAuth(): void
    {
        $this->client->request('POST', '/api/blog/posts', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'title' => 'Unauthorized Post',
            'content' => 'This should fail'
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testCreatePostWithInvalidData(): void
    {
        $this->client->request('POST', '/api/blog/posts', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'title' => '', // Empty title should fail validation
            'content' => ''
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $response);
    }

    public function testUpdatePost(): void
    {
        // Create test post
        $post = new Post();
        $post->setTitle('Original Title');
        $post->setContent('Original content');
        $post->setAuthor($this->adminUser);
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $this->client->request('PUT', '/api/blog/posts/' . $post->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'title' => 'Updated Title',
            'content' => 'Updated content'
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Post updated successfully', $response['message']);
    }

    public function testDeletePost(): void
    {
        // Create test post
        $post = new Post();
        $post->setTitle('Delete Test Post');
        $post->setContent('This post will be deleted');
        $post->setAuthor($this->adminUser);
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $postId = $post->getId();

        $this->client->request('DELETE', '/api/blog/posts/' . $postId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testListCategories(): void
    {
        // Create test categories
        $category1 = new Category();
        $category1->setName('Category 1');
        $category1->setDescription('Description 1');
        $this->entityManager->persist($category1);

        $category2 = new Category();
        $category2->setName('Category 2');
        $category2->setDescription('Description 2');
        $this->entityManager->persist($category2);

        $this->entityManager->flush();

        $this->client->request('GET', '/api/blog/categories');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('categories', $response);
        $this->assertIsArray($response['categories']);
    }

    public function testCreateCategory(): void
    {
        $this->client->request('POST', '/api/blog/categories', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'name' => 'New Category',
            'description' => 'Description of new category'
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
        $this->assertArrayHasKey('category', $response);
        $this->assertEquals('Category created successfully', $response['message']);
        $this->assertEquals('New Category', $response['category']['name']);
    }
}