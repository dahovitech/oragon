<?php

namespace App\Tests\Bundle\UserBundle\Controller\Admin;

use App\Bundle\UserBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserControllerTest extends WebTestCase
{
    private function createTestUser(): User
    {
        $client = static::createClient();
        $container = static::getContainer();
        
        $userRepository = $container->get('App\Bundle\UserBundle\Repository\UserRepository');
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        
        $testUser = new User();
        $testUser->setEmail('admin.test@oragon.local');
        $testUser->setFirstName('Admin');
        $testUser->setLastName('Test');
        $testUser->setRoles(['ROLE_ADMIN']);
        $testUser->setPassword($passwordHasher->hashPassword($testUser, 'password'));
        $testUser->setIsActive(true);

        $manager = $container->get('doctrine')->getManager();
        $manager->persist($testUser);
        $manager->flush();

        return $testUser;
    }

    public function testUserListPageRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/users/');

        $this->assertResponseRedirects('/login');
    }

    public function testUserListPageWithAuthenticatedAdmin(): void
    {
        $client = static::createClient();
        $testUser = $this->createTestUser();
        
        // Login with the test user
        $client->loginUser($testUser);
        
        // Test access to the page
        $crawler = $client->request('GET', '/admin/users/');
        
        $this->assertResponseIsSuccessful();
        $this->assertPageTitleContains('Gestion des utilisateurs');
    }

    public function testUserCreationForm(): void
    {
        $client = static::createClient();
        $testUser = $this->createTestUser();
        
        $client->loginUser($testUser);
        
        // Test the form page
        $crawler = $client->request('GET', '/admin/users/new');
        $this->assertResponseIsSuccessful();
        
        // Fill and submit the form
        $form = $crawler->selectButton('Créer')->form([
            'user[email]' => 'newuser@test.com',
            'user[firstName]' => 'New',
            'user[lastName]' => 'User',
            'user[plainPassword][first]' => 'password123',
            'user[plainPassword][second]' => 'password123',
            'user[roles]' => ['ROLE_USER'],
            'user[isActive]' => true,
        ]);
        
        $client->submit($form);
        
        $this->assertResponseRedirects('/admin/users/');
        
        // Verify the user was created
        $container = static::getContainer();
        $userRepository = $container->get('App\Bundle\UserBundle\Repository\UserRepository');
        $newUser = $userRepository->findOneBy(['email' => 'newuser@test.com']);
        
        $this->assertNotNull($newUser);
        $this->assertEquals('New', $newUser->getFirstName());
        $this->assertEquals('User', $newUser->getLastName());
    }

    public function testUserEditForm(): void
    {
        $client = static::createClient();
        $testUser = $this->createTestUser();
        
        $client->loginUser($testUser);
        
        // Create a user to edit
        $container = static::getContainer();
        $manager = $container->get('doctrine')->getManager();
        
        $userToEdit = new User();
        $userToEdit->setEmail('edit@test.com');
        $userToEdit->setFirstName('Edit');
        $userToEdit->setLastName('Me');
        $userToEdit->setRoles(['ROLE_USER']);
        $userToEdit->setPassword('hashedpassword');
        $userToEdit->setIsActive(true);
        
        $manager->persist($userToEdit);
        $manager->flush();
        
        // Test the edit form
        $crawler = $client->request('GET', '/admin/users/' . $userToEdit->getId() . '/edit');
        $this->assertResponseIsSuccessful();
        
        // Update the user
        $form = $crawler->selectButton('Modifier')->form([
            'user[firstName]' => 'Updated',
            'user[lastName]' => 'Name',
        ]);
        
        $client->submit($form);
        
        $this->assertResponseRedirects('/admin/users/');
        
        // Verify the changes
        $manager->refresh($userToEdit);
        $this->assertEquals('Updated', $userToEdit->getFirstName());
        $this->assertEquals('Name', $userToEdit->getLastName());
    }

    public function testUserDeletion(): void
    {
        $client = static::createClient();
        $testUser = $this->createTestUser();
        
        $client->loginUser($testUser);
        
        // Create a user to delete
        $container = static::getContainer();
        $manager = $container->get('doctrine')->getManager();
        
        $userToDelete = new User();
        $userToDelete->setEmail('delete@test.com');
        $userToDelete->setFirstName('Delete');
        $userToDelete->setLastName('Me');
        $userToDelete->setRoles(['ROLE_USER']);
        $userToDelete->setPassword('hashedpassword');
        $userToDelete->setIsActive(true);
        
        $manager->persist($userToDelete);
        $manager->flush();
        
        $userId = $userToDelete->getId();
        
        // Delete the user
        $crawler = $client->request('GET', '/admin/users/');
        $form = $crawler->filter('form[action*="/admin/users/' . $userId . '/delete"]')->form();
        
        $client->submit($form);
        
        $this->assertResponseRedirects('/admin/users/');
        
        // Verify the user was deleted
        $userRepository = $container->get('App\Bundle\UserBundle\Repository\UserRepository');
        $deletedUser = $userRepository->find($userId);
        
        $this->assertNull($deletedUser);
    }

    public function testCannotDeleteSelf(): void
    {
        $client = static::createClient();
        $testUser = $this->createTestUser();
        
        $client->loginUser($testUser);
        
        // Try to delete self
        $crawler = $client->request('GET', '/admin/users/');
        $forms = $crawler->filter('form[action*="/admin/users/' . $testUser->getId() . '/delete"]');
        
        if ($forms->count() > 0) {
            $form = $forms->form();
            $client->submit($form);
            
            $this->assertResponseRedirects('/admin/users/');
            $client->followRedirect();
            
            // Should show an error message
            $this->assertSelectorExists('.alert-danger');
        }
        
        // Verify the user still exists
        $container = static::getContainer();
        $userRepository = $container->get('App\Bundle\UserBundle\Repository\UserRepository');
        $existingUser = $userRepository->find($testUser->getId());
        
        $this->assertNotNull($existingUser);
    }
}