<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    private EntityManagerInterface $entityManager;
    private NotificationRepository $notificationRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        NotificationRepository $notificationRepository
    ) {
        $this->entityManager = $entityManager;
        $this->notificationRepository = $notificationRepository;
    }

    /**
     * Créer une nouvelle notification pour un utilisateur
     */
    public function createNotification(
        User $user,
        string $title,
        string $message,
        ?string $type = 'info',
        ?string $link = null,
        ?array $data = null,
        ?string $priority = 'normal'
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user)
                    ->setTitle($title)
                    ->setMessage($message)
                    ->setType($type)
                    ->setPriority($priority);

        if ($link) {
            $notification->setActionUrl($link);
        }

        if ($data) {
            $notification->setData($data);
        }

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    /**
     * Créer une notification pour une demande de prêt
     */
    public function createLoanApplicationNotification(
        User $user,
        string $status,
        int $loanApplicationId
    ): Notification {
        $titles = [
            'pending' => 'Demande de prêt reçue',
            'approved' => 'Demande de prêt approuvée',
            'rejected' => 'Demande de prêt refusée',
            'requires_documents' => 'Documents supplémentaires requis',
        ];

        $messages = [
            'pending' => 'Votre demande de prêt a été reçue et est en cours d\'examen.',
            'approved' => 'Félicitations ! Votre demande de prêt a été approuvée.',
            'rejected' => 'Votre demande de prêt n\'a pas pu être approuvée cette fois.',
            'requires_documents' => 'Veuillez fournir des documents supplémentaires pour votre demande.',
        ];

        return $this->createNotification(
            $user,
            $titles[$status] ?? 'Mise à jour de votre demande',
            $messages[$status] ?? 'Le statut de votre demande a été mis à jour.',
            'loan_application',
            "/loan-application/{$loanApplicationId}",
            ['loan_application_id' => $loanApplicationId, 'status' => $status],
            $status === 'approved' ? 'high' : 'normal'
        );
    }

    /**
     * Créer une notification pour un contrat de prêt
     */
    public function createLoanContractNotification(
        User $user,
        string $action,
        int $contractId
    ): Notification {
        $titles = [
            'created' => 'Contrat de prêt généré',
            'signed' => 'Contrat signé avec succès',
            'activated' => 'Prêt activé',
        ];

        $messages = [
            'created' => 'Votre contrat de prêt est prêt à être signé.',
            'signed' => 'Votre contrat a été signé avec succès.',
            'activated' => 'Votre prêt est maintenant actif.',
        ];

        return $this->createNotification(
            $user,
            $titles[$action] ?? 'Mise à jour du contrat',
            $messages[$action] ?? 'Une mise à jour concernant votre contrat.',
            'loan_contract',
            "/loan-contract/{$contractId}",
            ['contract_id' => $contractId, 'action' => $action],
            'high'
        );
    }

    /**
     * Créer une notification de paiement
     */
    public function createPaymentNotification(
        User $user,
        string $type,
        float $amount,
        ?int $paymentId = null
    ): Notification {
        $titles = [
            'received' => 'Paiement reçu',
            'overdue' => 'Paiement en retard',
            'reminder' => 'Rappel de paiement',
        ];

        $messages = [
            'received' => "Votre paiement de {$amount}€ a été reçu avec succès.",
            'overdue' => "Votre paiement de {$amount}€ est en retard.",
            'reminder' => "N'oubliez pas votre paiement de {$amount}€ qui arrive à échéance.",
        ];

        return $this->createNotification(
            $user,
            $titles[$type] ?? 'Notification de paiement',
            $messages[$type] ?? "Notification concernant un paiement de {$amount}€.",
            'payment',
            $paymentId ? "/payment/{$paymentId}" : '/payments',
            ['amount' => $amount, 'payment_id' => $paymentId, 'type' => $type],
            $type === 'overdue' ? 'high' : 'normal'
        );
    }

    /**
     * Récupérer les notifications non lues d'un utilisateur
     */
    public function getUnreadNotifications(User $user, int $limit = 10): array
    {
        return $this->notificationRepository->findBy(
            ['user' => $user, 'isRead' => false],
            ['createdAt' => 'DESC'],
            $limit
        );
    }

    /**
     * Récupérer toutes les notifications d'un utilisateur
     */
    public function getAllNotifications(User $user, int $limit = 50): array
    {
        return $this->notificationRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
            $limit
        );
    }

    /**
     * Marquer une notification comme lue
     */
    public function markAsRead(Notification $notification): void
    {
        if (!$notification->getIsRead()) {
            $notification->markAsRead();
            $this->entityManager->flush();
        }
    }

    /**
     * Marquer toutes les notifications d'un utilisateur comme lues
     */
    public function markAllAsRead(User $user): void
    {
        $notifications = $this->notificationRepository->findBy(
            ['user' => $user, 'isRead' => false]
        );

        foreach ($notifications as $notification) {
            $notification->markAsRead();
        }

        $this->entityManager->flush();
    }

    /**
     * Compter les notifications non lues d'un utilisateur
     */
    public function countUnreadNotifications(User $user): int
    {
        return $this->notificationRepository->count([
            'user' => $user,
            'isRead' => false
        ]);
    }

    /**
     * Supprimer une notification
     */
    public function deleteNotification(Notification $notification): void
    {
        $this->entityManager->remove($notification);
        $this->entityManager->flush();
    }

    /**
     * Supprimer les anciennes notifications (plus de 30 jours)
     */
    public function cleanOldNotifications(): int
    {
        $thirtyDaysAgo = new \DateTimeImmutable('-30 days');
        
        return $this->entityManager->createQuery(
            'DELETE FROM App\Entity\Notification n 
             WHERE n.createdAt < :date AND n.isRead = true'
        )
        ->setParameter('date', $thirtyDaysAgo)
        ->execute();
    }
}