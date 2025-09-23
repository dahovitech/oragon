<?php

namespace App\Tests\Bundle\ApiBundle\Controller;

use App\Bundle\UserBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        // Start a database transaction
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Roll back the transaction to clean up the database
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }

        parent::tearDown();
    }

    public function testRegister(): void
    {
        $this->client->request('POST', '/api/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@example.com',
            'password' => 'testpassword123',
            'firstName' => 'John',
            'lastName' => 'Doe'
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
        $this->assertArrayHasKey('user', $response);
        $this->assertEquals('User created successfully', $response['message']);
        $this->assertEquals('test@example.com', $response['user']['email']);
        $this->assertEquals('John', $response['user']['firstName']);
        $this->assertEquals('Doe', $response['user']['lastName']);
    }

    public function testRegisterWithInvalidData(): void
    {
        $this->client->request('POST', '/api/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'invalid-email',
            'password' => '',
            'firstName' => '',
            'lastName' => ''
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $response);
    }

    public function testLogin(): void
    {
        // Create a test user first
        $user = new User();
        $user->setEmail('login@example.com');
        $user->setFirstName('Jane');
        $user->setLastName('Doe');
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'testpassword123');
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'login@example.com',
            'password' => 'testpassword123'
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $response);
        $this->assertArrayHasKey('user', $response);
        $this->assertIsString($response['token']);
        $this->assertEquals('login@example.com', $response['user']['email']);
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $this->client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword'
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Invalid credentials', $response['error']);
    }

    public function testProfile(): void
    {
        // Create a test user and get JWT token
        $user = new User();
        $user->setEmail('profile@example.com');
        $user->setFirstName('Profile');
        $user->setLastName('User');
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'testpassword123');
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Login to get token
        $this->client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'profile@example.com',
            'password' => 'testpassword123'
        ]));

        $loginResponse = json_decode($this->client->getResponse()->getContent(), true);
        $token = $loginResponse['token'];

        // Test profile endpoint
        $this->client->request('GET', '/api/profile', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json'
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('profile@example.com', $response['email']);
        $this->assertEquals('Profile', $response['firstName']);
        $this->assertEquals('User', $response['lastName']);
    }

    public function testProfileWithoutToken(): void
    {
        $this->client->request('GET', '/api/profile');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testLoginMissingFields(): void
    {
        $this->client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@example.com'
            // Missing password
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Email and password are required', $response['error']);
    }
}