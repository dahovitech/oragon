<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\LoanApplication;
use App\Entity\LoanContract;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class NotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private LoggerInterface $logger
    ) {}

    // === Document Notifications ===

    public function sendDocumentUploaded(User $user, Document $document): void
    {
        try {
            $email = (new Email())
                ->from('noreply@oragon.sn')
                ->to($user->getEmail())
                ->subject('Document téléchargé avec succès - Oragon')
                ->html($this->twig->render('emails/document_uploaded.html.twig', [
                    'user' => $user,
                    'document' => $document,
                ]));

            $this->mailer->send($email);
            $this->logger->info('Document uploaded notification sent', ['user_id' => $user->getId()]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send document uploaded notification', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendDocumentApproved(User $user, Document $document): void
    {
        try {
            $email = (new Email())
                ->from('noreply@oragon.sn')
                ->to($user->getEmail())
                ->subject('Document approuvé - Oragon')
                ->html($this->twig->render('emails/document_approved.html.twig', [
                    'user' => $user,
                    'document' => $document,
                ]));

            $this->mailer->send($email);
            $this->logger->info('Document approved notification sent', ['user_id' => $user->getId()]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send document approved notification', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendDocumentRejected(User $user, Document $document): void
    {
        try {
            $email = (new Email())
                ->from('noreply@oragon.sn')
                ->to($user->getEmail())
                ->subject('Document rejeté - Oragon')
                ->html($this->twig->render('emails/document_rejected.html.twig', [
                    'user' => $user,
                    'document' => $document,
                ]));

            $this->mailer->send($email);
            $this->logger->info('Document rejected notification sent', ['user_id' => $user->getId()]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send document rejected notification', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendDocumentPending(User $user, Document $document): void
    {
        try {
            $email = (new Email())
                ->from('noreply@oragon.sn')
                ->to($user->getEmail())
                ->subject('Document en cours de vérification - Oragon')
                ->html($this->twig->render('emails/document_pending.html.twig', [
                    'user' => $user,
                    'document' => $document,
                ]));

            $this->mailer->send($email);
            $this->logger->info('Document pending notification sent', ['user_id' => $user->getId()]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send document pending notification', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    // === Loan Application Notifications ===

    public function sendLoanApplicationSubmitted(LoanApplication $application): void
    {
        $user = $application->getUser();
        
        try {
            $email = (new Email())
                ->from('noreply@oragon.sn')
                ->to($user->getEmail())
                ->subject('Votre demande de prêt a été soumise - Oragon')
                ->html($this->twig->render('emails/loan_application_submitted.html.twig', [
                    'user' => $user,
                    'application' => $application
                ]));

            $this->mailer->send($email);
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
                ->from('noreply@oragon.sn')
                ->to($user->getEmail())
                ->subject('Votre demande de prêt est en cours d\'étude - Oragon')
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
                ->from('noreply@oragon.sn')
                ->to($user->getEmail())
                ->subject('Félicitations ! Votre demande de prêt a été approuvée - Oragon')
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
                ->from('noreply@oragon.sn')
                ->to($user->getEmail())
                ->subject('Information concernant votre demande de prêt - Oragon')
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

    // === Contract Notifications ===

    public function sendContractGenerated(User $user, LoanContract $contract): void
    {
        try {
            $email = (new Email())
                ->from('noreply@oragon.sn')
                ->to($user->getEmail())
                ->subject('Votre contrat de prêt a été généré - Oragon')
                ->html($this->twig->render('emails/contract_generated.html.twig', [
                    'user' => $user,
                    'contract' => $contract,
                    'loan_application' => $contract->getLoanApplication(),
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

    public function sendContractForSigning(User $user, LoanContract $contract): void
    {
        try {
            $email = (new Email())
                ->from('noreply@oragon.sn')
                ->to($user->getEmail())
                ->subject('Votre contrat de prêt est prêt à être signé - Oragon')
                ->html($this->twig->render('emails/contract_for_signing.html.twig', [
                    'user' => $user,
                    'contract' => $contract,
                    'loan_application' => $contract->getLoanApplication(),
                    'signing_url' => 'https://oragon.sn/contracts/' . $contract->getId(),
                ]));

            $this->mailer->send($email);
            $this->logger->info('Contract for signing notification sent', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send contract for signing notification', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendContractSigned(User $user, LoanContract $contract): void
    {
        try {
            $email = (new Email())
                ->from('noreply@oragon.sn')
                ->to($user->getEmail())
                ->subject('Contrat signé - Activation en cours - Oragon')
                ->html($this->twig->render('emails/contract_signed.html.twig', [
                    'user' => $user,
                    'contract' => $contract,
                    'loan_application' => $contract->getLoanApplication(),
                ]));

            $this->mailer->send($email);
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

    public function sendContractActivated(User $user, LoanContract $contract): void
    {
        try {
            $email = (new Email())
                ->from('noreply@oragon.sn')
                ->to($user->getEmail())
                ->subject('Votre prêt est maintenant actif - Oragon')
                ->html($this->twig->render('emails/contract_activated.html.twig', [
                    'user' => $user,
                    'contract' => $contract,
                    'loan_application' => $contract->getLoanApplication(),
                ]));

            $this->mailer->send($email);
            $this->logger->info('Contract activated notification sent', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send contract activated notification', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendLoanDisbursed(User $user, LoanContract $contract): void
    {
        try {
            $email = (new Email())
                ->from('noreply@oragon.sn')
                ->to($user->getEmail())
                ->subject('Votre prêt a été débloqué - Oragon')
                ->html($this->twig->render('emails/loan_disbursed.html.twig', [
                    'user' => $user,
                    'contract' => $contract,
                    'loan_application' => $contract->getLoanApplication(),
                ]));

            $this->mailer->send($email);
            $this->logger->info('Loan disbursed notification sent', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send loan disbursed notification', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendPaymentReminder(User $user, LoanContract $contract, int $daysUntilDue = 5): void
    {
        try {
            $email = (new Email())
                ->from('noreply@oragon.sn')
                ->to($user->getEmail())
                ->subject('Rappel d\'échéance - Prochaine mensualité dans ' . $daysUntilDue . ' jours - Oragon')
                ->html($this->twig->render('emails/payment_reminder.html.twig', [
                    'user' => $user,
                    'contract' => $contract,
                    'days_until_due' => $daysUntilDue,
                ]));

            $this->mailer->send($email);
            $this->logger->info('Payment reminder sent', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber(),
                'days_until_due' => $daysUntilDue
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send payment reminder', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendPaymentOverdue(User $user, LoanContract $contract, int $daysPastDue): void
    {
        try {
            $email = (new Email())
                ->from('noreply@oragon.sn')
                ->to($user->getEmail())
                ->subject('Échéance impayée - Action requise - Oragon')
                ->html($this->twig->render('emails/payment_overdue.html.twig', [
                    'user' => $user,
                    'contract' => $contract,
                    'days_past_due' => $daysPastDue,
                ]));

            $this->mailer->send($email);
            $this->logger->info('Payment overdue notification sent', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber(),
                'days_past_due' => $daysPastDue
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send payment overdue notification', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendContractCompleted(User $user, LoanContract $contract): void
    {
        try {
            $email = (new Email())
                ->from('noreply@oragon.sn')
                ->to($user->getEmail())
                ->subject('Félicitations ! Votre prêt est entièrement remboursé - Oragon')
                ->html($this->twig->render('emails/contract_completed.html.twig', [
                    'user' => $user,
                    'contract' => $contract,
                    'loan_application' => $contract->getLoanApplication(),
                ]));

            $this->mailer->send($email);
            $this->logger->info('Contract completed notification sent', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send contract completed notification', [
                'user_id' => $user->getId(),
                'contract_number' => $contract->getContractNumber(),
                'error' => $e->getMessage()
            ]);
        }
    }

    // === General User Notifications ===

    public function sendWelcomeEmail(User $user): void
    {
        try {
            $email = (new Email())
                ->from('noreply@oragon.sn')
                ->to($user->getEmail())
                ->subject('Bienvenue chez Oragon !')
                ->html($this->twig->render('emails/welcome.html.twig', [
                    'user' => $user
                ]));

            $this->mailer->send($email);
            $this->logger->info('Welcome email sent', ['user_id' => $user->getId()]);
            
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
                ->from('noreply@oragon.sn')
                ->to($user->getEmail())
                ->subject('Réinitialisation de votre mot de passe - Oragon')
                ->html($this->twig->render('emails/password_reset.html.twig', [
                    'user' => $user,
                    'resetToken' => $resetToken
                ]));

            $this->mailer->send($email);
            $this->logger->info('Password reset email sent', ['user_id' => $user->getId()]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send password reset email', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }
}