<?php

namespace App\Service;

use App\Entity\AccountVerification;
use App\Entity\LoanApplication;
use App\Entity\User;
use App\Enum\VerificationStatus;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class NotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private string $fromEmail = 'noreply@edgeloan.com'
    ) {}

    public function sendVerificationSubmitted(AccountVerification $verification): void
    {
        $user = $verification->getUser();
        
        $email = (new Email())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('EdgeLoan - Demande de vérification reçue')
            ->html($this->twig->render('emails/verification_submitted.html.twig', [
                'user' => $user,
                'verification' => $verification
            ]));

        $this->mailer->send($email);
    }

    public function sendVerificationStatusUpdate(AccountVerification $verification): void
    {
        $user = $verification->getUser();
        
        $subject = match($verification->getStatus()) {
            VerificationStatus::VERIFIED => 'EdgeLoan - Vérification approuvée ✅',
            VerificationStatus::REJECTED => 'EdgeLoan - Vérification rejetée ❌',
            default => 'EdgeLoan - Mise à jour de votre vérification'
        };
        
        $email = (new Email())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject($subject)
            ->html($this->twig->render('emails/verification_status_update.html.twig', [
                'user' => $user,
                'verification' => $verification
            ]));

        $this->mailer->send($email);
    }

    public function sendAccountFullyVerified(User $user): void
    {
        $email = (new Email())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('🎉 EdgeLoan - Votre compte est maintenant vérifié!')
            ->html($this->twig->render('emails/account_fully_verified.html.twig', [
                'user' => $user
            ]));

        $this->mailer->send($email);
    }

    public function sendLoanApplicationSubmitted(LoanApplication $application): void
    {
        $user = $application->getUser();
        
        $email = (new Email())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('EdgeLoan - Votre demande de prêt a été reçue')
            ->html($this->twig->render('emails/loan_application_submitted.html.twig', [
                'user' => $user,
                'application' => $application
            ]));

        $this->mailer->send($email);
    }

    public function sendLoanApplicationStatusUpdate(LoanApplication $application): void
    {
        $user = $application->getUser();
        
        $subject = match($application->getStatus()->value) {
            'APPROVED' => 'EdgeLoan - Félicitations! Votre prêt est approuvé 🎉',
            'REJECTED' => 'EdgeLoan - Décision concernant votre demande de prêt',
            'UNDER_REVIEW' => 'EdgeLoan - Votre demande est en cours d\'étude',
            default => 'EdgeLoan - Mise à jour de votre demande de prêt'
        };
        
        $email = (new Email())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject($subject)
            ->html($this->twig->render('emails/loan_application_status_update.html.twig', [
                'user' => $user,
                'application' => $application
            ]));

        $this->mailer->send($email);
    }

    public function sendPaymentReminder(User $user, $payment): void
    {
        $email = (new Email())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('EdgeLoan - Rappel d\'échéance de paiement')
            ->html($this->twig->render('emails/payment_reminder.html.twig', [
                'user' => $user,
                'payment' => $payment
            ]));

        $this->mailer->send($email);
    }

    public function sendPaymentConfirmation(User $user, $payment): void
    {
        $email = (new Email())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('EdgeLoan - Confirmation de paiement')
            ->html($this->twig->render('emails/payment_confirmation.html.twig', [
                'user' => $user,
                'payment' => $payment
            ]));

        $this->mailer->send($email);
    }

    public function sendWelcomeEmail(User $user): void
    {
        $email = (new Email())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('Bienvenue sur EdgeLoan! 🚀')
            ->html($this->twig->render('emails/welcome.html.twig', [
                'user' => $user
            ]));

        $this->mailer->send($email);
    }

    public function sendPasswordResetEmail(User $user, string $resetToken): void
    {
        $email = (new Email())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('EdgeLoan - Réinitialisation de votre mot de passe')
            ->html($this->twig->render('emails/password_reset.html.twig', [
                'user' => $user,
                'resetToken' => $resetToken
            ]));

        $this->mailer->send($email);
    }
}