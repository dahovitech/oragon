<?php

namespace App\Tests\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class NotificationServiceTest extends TestCase
{
    private NotificationService $notificationService;
    private EntityManagerInterface&MockObject $entityManager;
    private NotificationRepository&MockObject $notificationRepository;
    private User $user;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->notificationRepository = $this->createMock(NotificationRepository::class);
        
        $this->notificationService = new NotificationService(
            $this->entityManager,
            $this->notificationRepository
        );

        $this->user = new User();
        $this->user->setEmail('test@example.com')
                   ->setFirstName('John')
                   ->setLastName('Doe');
    }

    public function testCreateNotification(): void
    {
        $title = 'Test Notification';
        $message = 'This is a test notification';
        $type = 'info';
        $link = '/test-link';
        $data = ['key' => 'value'];

        $this->entityManager->expects($this->once())
                           ->method('persist')
                           ->with($this->isInstanceOf(Notification::class));

        $this->entityManager->expects($this->once())
                           ->method('flush');

        $notification = $this->notificationService->createNotification(
            $this->user,
            $title,
            $message,
            $type,
            $link,
            $data
        );

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals($this->user, $notification->getUser());
        $this->assertEquals($title, $notification->getTitle());
        $this->assertEquals($message, $notification->getMessage());
        $this->assertEquals($type, $notification->getType());
        $this->assertEquals($link, $notification->getActionUrl());
        $this->assertEquals($data, $notification->getData());
        $this->assertFalse($notification->getIsRead());
        $this->assertInstanceOf(\DateTimeImmutable::class, $notification->getCreatedAt());
    }

    public function testCreateLoanApplicationNotification(): void
    {
        $status = 'approved';
        $loanApplicationId = 123;

        $this->entityManager->expects($this->once())
                           ->method('persist')
                           ->with($this->isInstanceOf(Notification::class));

        $this->entityManager->expects($this->once())
                           ->method('flush');

        $notification = $this->notificationService->createLoanApplicationNotification(
            $this->user,
            $status,
            $loanApplicationId
        );

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals($this->user, $notification->getUser());
        $this->assertEquals('Demande de prêt approuvée', $notification->getTitle());
        $this->assertEquals('Félicitations ! Votre demande de prêt a été approuvée.', $notification->getMessage());
        $this->assertEquals('loan_application', $notification->getType());
        $this->assertEquals("/loan-application/{$loanApplicationId}", $notification->getActionUrl());
        $this->assertEquals('high', $notification->getPriority());
    }

    public function testCreateLoanContractNotification(): void
    {
        $action = 'created';
        $contractId = 456;

        $this->entityManager->expects($this->once())
                           ->method('persist')
                           ->with($this->isInstanceOf(Notification::class));

        $this->entityManager->expects($this->once())
                           ->method('flush');

        $notification = $this->notificationService->createLoanContractNotification(
            $this->user,
            $action,
            $contractId
        );

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals($this->user, $notification->getUser());
        $this->assertEquals('Contrat de prêt généré', $notification->getTitle());
        $this->assertEquals('Votre contrat de prêt est prêt à être signé.', $notification->getMessage());
        $this->assertEquals('loan_contract', $notification->getType());
        $this->assertEquals("/loan-contract/{$contractId}", $notification->getActionUrl());
        $this->assertEquals('high', $notification->getPriority());
    }

    public function testCreatePaymentNotification(): void
    {
        $type = 'received';
        $amount = 500.00;
        $paymentId = 789;

        $this->entityManager->expects($this->once())
                           ->method('persist')
                           ->with($this->isInstanceOf(Notification::class));

        $this->entityManager->expects($this->once())
                           ->method('flush');

        $notification = $this->notificationService->createPaymentNotification(
            $this->user,
            $type,
            $amount,
            $paymentId
        );

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals($this->user, $notification->getUser());
        $this->assertEquals('Paiement reçu', $notification->getTitle());
        $this->assertEquals('Votre paiement de 500€ a été reçu avec succès.', $notification->getMessage());
        $this->assertEquals('payment', $notification->getType());
        $this->assertEquals("/payment/{$paymentId}", $notification->getActionUrl());
        $this->assertEquals('normal', $notification->getPriority());
    }

    public function testGetUnreadNotifications(): void
    {
        $limit = 10;
        $expectedNotifications = [];

        $this->notificationRepository->expects($this->once())
                                    ->method('findBy')
                                    ->with(
                                        ['user' => $this->user, 'isRead' => false],
                                        ['createdAt' => 'DESC'],
                                        $limit
                                    )
                                    ->willReturn($expectedNotifications);

        $result = $this->notificationService->getUnreadNotifications($this->user, $limit);

        $this->assertEquals($expectedNotifications, $result);
    }

    public function testCountUnreadNotifications(): void
    {
        $expectedCount = 5;

        $this->notificationRepository->expects($this->once())
                                    ->method('count')
                                    ->with(['user' => $this->user, 'isRead' => false])
                                    ->willReturn($expectedCount);

        $result = $this->notificationService->countUnreadNotifications($this->user);

        $this->assertEquals($expectedCount, $result);
    }

    public function testMarkAsRead(): void
    {
        $notification = new Notification();
        $notification->setUser($this->user)
                    ->setTitle('Test')
                    ->setMessage('Test message')
                    ->setType('info');

        $this->assertFalse($notification->getIsRead());
        $this->assertNull($notification->getReadAt());

        $this->entityManager->expects($this->once())
                           ->method('flush');

        $this->notificationService->markAsRead($notification);

        $this->assertTrue($notification->getIsRead());
        $this->assertInstanceOf(\DateTimeImmutable::class, $notification->getReadAt());
    }

    public function testMarkAsReadAlreadyRead(): void
    {
        $notification = new Notification();
        $notification->setUser($this->user)
                    ->setTitle('Test')
                    ->setMessage('Test message')
                    ->setType('info')
                    ->markAsRead();

        $this->assertTrue($notification->getIsRead());

        // Should not call flush if already read
        $this->entityManager->expects($this->never())
                           ->method('flush');

        $this->notificationService->markAsRead($notification);
    }

    public function testMarkAllAsRead(): void
    {
        $notification1 = new Notification();
        $notification1->setUser($this->user)
                     ->setTitle('Test 1')
                     ->setMessage('Test message 1')
                     ->setType('info');

        $notification2 = new Notification();
        $notification2->setUser($this->user)
                     ->setTitle('Test 2')
                     ->setMessage('Test message 2')
                     ->setType('info');

        $unreadNotifications = [$notification1, $notification2];

        $this->notificationRepository->expects($this->once())
                                    ->method('findBy')
                                    ->with(['user' => $this->user, 'isRead' => false])
                                    ->willReturn($unreadNotifications);

        $this->entityManager->expects($this->once())
                           ->method('flush');

        $this->notificationService->markAllAsRead($this->user);

        $this->assertTrue($notification1->getIsRead());
        $this->assertTrue($notification2->getIsRead());
    }

    public function testDeleteNotification(): void
    {
        $notification = new Notification();
        $notification->setUser($this->user)
                    ->setTitle('Test')
                    ->setMessage('Test message')
                    ->setType('info');

        $this->entityManager->expects($this->once())
                           ->method('remove')
                           ->with($notification);

        $this->entityManager->expects($this->once())
                           ->method('flush');

        $this->notificationService->deleteNotification($notification);
    }

    public function testCleanOldNotifications(): void
    {
        $expectedDeletedCount = 10;

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
              ->method('setParameter')
              ->with('date', $this->isInstanceOf(\DateTimeImmutable::class))
              ->willReturnSelf();
        
        $query->expects($this->once())
              ->method('execute')
              ->willReturn($expectedDeletedCount);

        $this->entityManager->expects($this->once())
                           ->method('createQuery')
                           ->with($this->stringContains('DELETE FROM App\Entity\Notification'))
                           ->willReturn($query);

        $result = $this->notificationService->cleanOldNotifications();

        $this->assertEquals($expectedDeletedCount, $result);
    }
}