<?php

namespace App\Service;

use App\Entity\LoanApplication;
use App\Entity\LoanContract;
use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class NotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private LoggerInterface $logger
    ) {}

    public function sendContractGenerated(LoanContract $contract): void
    {
        $user = $contract->getLoanApplication()->getUser();
        
        try {
            $email = (new Email())
                ->from('noreply@edgeloan.fr')
                ->to($user->getEmail())
                ->subject('Votre contrat de prêt est prêt - EdgeLoan')
                ->html($this->twig->render('emails/contract_generated.html.twig', [
                    'user' => $user,
                    'contract' => $contract,
                    'application' => $contract->getLoanApplication()
                ]));

            $this->mailer->send($email);
            
            $this->logger->info('Contract generated notification sent', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send contract generated notification', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendContractSigned(LoanApplication $application): void
    {
        $user = $application->getUser();
        $contract = $application->getLoanContract();
        
        try {
            $email = (new Email())
                ->from('noreply@edgeloan.fr')
                ->to($user->getEmail())
                ->subject('Félicitations ! Votre contrat a été signé - EdgeLoan')
                ->html($this->twig->render('emails/contract_signed.html.twig', [
                    'user' => $user,
                    'contract' => $contract,
                    'application' => $application
                ]));

            $this->mailer->send($email);
            
            // Notification à l'équipe interne
            $this->sendInternalNotification('contract_signed', [
                'user' => $user,
                'contract' => $contract,
                'application' => $application
            ]);
            
            $this->logger->info('Contract signed notification sent', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send contract signed notification', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendPaymentReminder(LoanContract $contract, array $overduePayments): void
    {
        $user = $contract->getLoanApplication()->getUser();
        
        try {
            $email = (new Email())
                ->from('noreply@edgeloan.fr')
                ->to($user->getEmail())
                ->subject('Rappel d\'échéance - EdgeLoan')
                ->html($this->twig->render('emails/payment_reminder.html.twig', [
                    'user' => $user,
                    'contract' => $contract,
                    'overduePayments' => $overduePayments
                ]));

            $this->mailer->send($email);
            
            $this->logger->info('Payment reminder sent', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber(),
                'overdue_count' => count($overduePayments)
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send payment reminder', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendPaymentConfirmation(LoanContract $contract, array $payment): void
    {
        $user = $contract->getLoanApplication()->getUser();
        
        try {
            $email = (new Email())
                ->from('noreply@edgeloan.fr')
                ->to($user->getEmail())
                ->subject('Confirmation de paiement - EdgeLoan')
                ->html($this->twig->render('emails/payment_confirmation.html.twig', [
                    'user' => $user,
                    'contract' => $contract,
                    'payment' => $payment
                ]));

            $this->mailer->send($email);
            
            $this->logger->info('Payment confirmation sent', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber(),
                'payment_amount' => $payment['amount']
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send payment confirmation', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendLoanFullyRepaid(LoanContract $contract): void
    {
        $user = $contract->getLoanApplication()->getUser();
        
        try {
            $email = (new Email())
                ->from('noreply@edgeloan.fr')
                ->to($user->getEmail())
                ->subject('Félicitations ! Votre prêt est soldé - EdgeLoan')
                ->html($this->twig->render('emails/loan_fully_repaid.html.twig', [
                    'user' => $user,
                    'contract' => $contract
                ]));

            $this->mailer->send($email);
            
            $this->logger->info('Loan fully repaid notification sent', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send loan fully repaid notification', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendEarlyRepaymentConfirmation(LoanContract $contract, float $amount): void
    {
        $user = $contract->getLoanApplication()->getUser();
        
        try {
            $email = (new Email())
                ->from('noreply@edgeloan.fr')
                ->to($user->getEmail())
                ->subject('Confirmation de remboursement anticipé - EdgeLoan')
                ->html($this->twig->render('emails/early_repayment_confirmation.html.twig', [
                    'user' => $user,
                    'contract' => $contract,
                    'repaymentAmount' => $amount
                ]));

            $this->mailer->send($email);
            
            $this->logger->info('Early repayment confirmation sent', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber(),
                'amount' => $amount
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send early repayment confirmation', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendApplicationStatusUpdate(LoanApplication $application, string $newStatus): void
    {
        $user = $application->getUser();
        
        try {
            $email = (new Email())
                ->from('noreply@edgeloan.fr')
                ->to($user->getEmail())
                ->subject('Mise à jour de votre demande de prêt - EdgeLoan')
                ->html($this->twig->render('emails/application_status_update.html.twig', [
                    'user' => $user,
                    'application' => $application,
                    'newStatus' => $newStatus
                ]));

            $this->mailer->send($email);
            
            $this->logger->info('Application status update sent', [
                'user_id' => $user->getId(),
                'application_id' => $application->getId(),
                'new_status' => $newStatus
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send application status update', [
                'user_id' => $user->getId(),
                'application_id' => $application->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendLoanApplicationSubmitted(LoanApplication $application): void
    {
        $user = $application->getUser();
        
        try {
            $email = (new Email())
                ->from('noreply@edgeloan.fr')
                ->to($user->getEmail())
                ->subject('Votre demande de prêt a été soumise - EdgeLoan')
                ->html($this->twig->render('emails/loan_application_submitted.html.twig', [
                    'user' => $user,
                    'application' => $application
                ]));

            $this->mailer->send($email);
            
            // Notification interne
            $this->sendInternalNotification('loan_application_submitted', [
                'user' => $user,
                'application' => $application
            ]);
            
            $this->logger->info('Loan application submitted notification sent', [
                'user_id' => $user->getId(),
                'application_id' => $application->getId()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send loan application submitted notification', [
                'user_id' => $user->getId(),
                'application_id' => $application->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendLoanApplicationUnderReview(LoanApplication $application): void
    {
        $user = $application->getUser();
        
        try {
            $email = (new Email())
                ->from('noreply@edgeloan.fr')
                ->to($user->getEmail())
                ->subject('Votre demande de prêt est en cours d\'étude - EdgeLoan')
                ->html($this->twig->render('emails/loan_application_under_review.html.twig', [
                    'user' => $user,
                    'application' => $application
                ]));

            $this->mailer->send($email);
            
            $this->logger->info('Loan application under review notification sent', [
                'user_id' => $user->getId(),
                'application_id' => $application->getId()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send loan application under review notification', [
                'user_id' => $user->getId(),
                'application_id' => $application->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendLoanApplicationApproved(LoanApplication $application): void
    {
        $user = $application->getUser();
        
        try {
            $email = (new Email())
                ->from('noreply@edgeloan.fr')
                ->to($user->getEmail())
                ->subject('Félicitations ! Votre demande de prêt a été approuvée - EdgeLoan')
                ->html($this->twig->render('emails/loan_application_approved.html.twig', [
                    'user' => $user,
                    'application' => $application
                ]));

            $this->mailer->send($email);
            
            $this->logger->info('Loan application approved notification sent', [
                'user_id' => $user->getId(),
                'application_id' => $application->getId()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send loan application approved notification', [
                'user_id' => $user->getId(),
                'application_id' => $application->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendLoanApplicationRejected(LoanApplication $application, string $reason = ''): void
    {
        $user = $application->getUser();
        
        try {
            $email = (new Email())
                ->from('noreply@edgeloan.fr')
                ->to($user->getEmail())
                ->subject('Information concernant votre demande de prêt - EdgeLoan')
                ->html($this->twig->render('emails/loan_application_rejected.html.twig', [
                    'user' => $user,
                    'application' => $application,
                    'rejectionReason' => $reason
                ]));

            $this->mailer->send($email);
            
            $this->logger->info('Loan application rejected notification sent', [
                'user_id' => $user->getId(),
                'application_id' => $application->getId(),
                'reason' => $reason
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send loan application rejected notification', [
                'user_id' => $user->getId(),
                'application_id' => $application->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendLoanApplicationPendingDocuments(LoanApplication $application, array $requiredDocuments): void
    {
        $user = $application->getUser();
        
        try {
            $email = (new Email())
                ->from('noreply@edgeloan.fr')
                ->to($user->getEmail())
                ->subject('Documents requis pour votre demande de prêt - EdgeLoan')
                ->html($this->twig->render('emails/loan_application_pending_documents.html.twig', [
                    'user' => $user,
                    'application' => $application,
                    'requiredDocuments' => $requiredDocuments
                ]));

            $this->mailer->send($email);
            
            $this->logger->info('Loan application pending documents notification sent', [
                'user_id' => $user->getId(),
                'application_id' => $application->getId(),
                'documents_count' => count($requiredDocuments)
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send loan application pending documents notification', [
                'user_id' => $user->getId(),
                'application_id' => $application->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendWelcomeEmail(User $user): void
    {
        try {
            $email = (new Email())
                ->from('noreply@edgeloan.fr')
                ->to($user->getEmail())
                ->subject('Bienvenue chez EdgeLoan !')
                ->html($this->twig->render('emails/welcome.html.twig', [
                    'user' => $user
                ]));

            $this->mailer->send($email);
            
            $this->logger->info('Welcome email sent', [
                'user_id' => $user->getId()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send welcome email', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendPasswordResetEmail(User $user, string $resetToken): void
    {
        try {
            $email = (new Email())
                ->from('noreply@edgeloan.fr')
                ->to($user->getEmail())
                ->subject('Réinitialisation de votre mot de passe - EdgeLoan')
                ->html($this->twig->render('emails/password_reset.html.twig', [
                    'user' => $user,
                    'resetToken' => $resetToken
                ]));

            $this->mailer->send($email);
            
            $this->logger->info('Password reset email sent', [
                'user_id' => $user->getId()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send password reset email', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    private function sendInternalNotification(string $type, array $data): void
    {
        try {
            $email = (new Email())
                ->from('noreply@edgeloan.fr')
                ->to('team@edgeloan.fr')
                ->subject("Notification interne : {$type}")
                ->html($this->twig->render('emails/internal_notification.html.twig', [
                    'type' => $type,
                    'data' => $data,
                    'timestamp' => new \DateTime()
                ]));

            $this->mailer->send($email);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send internal notification', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendBulkNotification(array $users, string $subject, string $template, array $data = []): int
    {
        $sentCount = 0;
        
        foreach ($users as $user) {
            try {
                $email = (new Email())
                    ->from('noreply@edgeloan.fr')
                    ->to($user->getEmail())
                    ->subject($subject)
                    ->html($this->twig->render($template, array_merge($data, ['user' => $user])));

                $this->mailer->send($email);
                $sentCount++;
                
            } catch (\Exception $e) {
                $this->logger->error('Failed to send bulk notification', [
                    'user_id' => $user->getId(),
                    'template' => $template,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->logger->info('Bulk notification completed', [
            'template' => $template,
            'total_users' => count($users),
            'sent_count' => $sentCount
        ]);
        
        return $sentCount;
    }
}