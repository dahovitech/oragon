<?php

namespace App\Bundle\NotificationBundle\Service;

use App\Bundle\NotificationBundle\Entity\Notification;
use App\Bundle\NotificationBundle\Entity\EmailTemplate;
use App\Bundle\NotificationBundle\Repository\EmailTemplateRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Twig\Environment;

class EmailService
{
    private MailerInterface $mailer;
    private EmailTemplateRepository $templateRepository;
    private Environment $twig;
    private string $defaultFromEmail;
    private string $defaultFromName;

    public function __construct(
        MailerInterface $mailer,
        EmailTemplateRepository $templateRepository,
        Environment $twig,
        string $defaultFromEmail,
        string $defaultFromName
    ) {
        $this->mailer = $mailer;
        $this->templateRepository = $templateRepository;
        $this->twig = $twig;
        $this->defaultFromEmail = $defaultFromEmail;
        $this->defaultFromName = $defaultFromName;
    }

    /**
     * Send notification email using template
     */
    public function sendNotificationEmail(Notification $notification): bool
    {
        try {
            $template = $this->templateRepository->findActiveTemplate($notification->getType());
            
            if (!$template) {
                // Fallback to basic email
                return $this->sendBasicNotificationEmail($notification);
            }

            return $this->sendTemplatedEmail($notification, $template);
        } catch (\Exception $e) {
            error_log("Failed to send notification email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email using template
     */
    public function sendTemplatedEmail(Notification $notification, EmailTemplate $template): bool
    {
        $variables = $this->prepareTemplateVariables($notification);
        $processedContent = $template->processContent($variables);

        $email = (new Email())
            ->from(new Address($this->defaultFromEmail, $this->defaultFromName))
            ->to($notification->getUserEmail())
            ->subject($processedContent['subject'])
            ->html($processedContent['html']);

        if ($processedContent['text']) {
            $email->text($processedContent['text']);
        }

        $this->mailer->send($email);
        return true;
    }

    /**
     * Send basic notification email without template
     */
    public function sendBasicNotificationEmail(Notification $notification): bool
    {
        $htmlContent = $this->renderBasicEmailTemplate($notification);

        $email = (new Email())
            ->from(new Address($this->defaultFromEmail, $this->defaultFromName))
            ->to($notification->getUserEmail())
            ->subject($notification->getTitle())
            ->html($htmlContent)
            ->text(strip_tags($notification->getMessage()));

        $this->mailer->send($email);
        return true;
    }

    /**
     * Send custom email
     */
    public function sendCustomEmail(
        string $to,
        string $subject,
        string $templateName,
        array $variables = [],
        ?string $fromEmail = null,
        ?string $fromName = null
    ): bool {
        try {
            $template = $this->templateRepository->findActiveTemplate($templateName);
            
            if (!$template) {
                throw new \InvalidArgumentException("Template '{$templateName}' not found");
            }

            $processedContent = $template->processContent($variables);

            $email = (new Email())
                ->from(new Address($fromEmail ?? $this->defaultFromEmail, $fromName ?? $this->defaultFromName))
                ->to($to)
                ->subject($processedContent['subject'])
                ->html($processedContent['html']);

            if ($processedContent['text']) {
                $email->text($processedContent['text']);
            }

            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            error_log("Failed to send custom email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send bulk emails
     */
    public function sendBulkEmails(
        array $recipients,
        string $subject,
        string $templateName,
        array $variables = [],
        ?string $fromEmail = null,
        ?string $fromName = null
    ): array {
        $results = [];
        $template = $this->templateRepository->findActiveTemplate($templateName);
        
        if (!$template) {
            throw new \InvalidArgumentException("Template '{$templateName}' not found");
        }

        foreach ($recipients as $recipient) {
            $recipientEmail = is_array($recipient) ? $recipient['email'] : $recipient;
            $recipientVariables = is_array($recipient) ? array_merge($variables, $recipient['variables'] ?? []) : $variables;

            try {
                $success = $this->sendCustomEmail(
                    $recipientEmail,
                    $subject,
                    $templateName,
                    $recipientVariables,
                    $fromEmail,
                    $fromName
                );
                $results[$recipientEmail] = $success;
            } catch (\Exception $e) {
                $results[$recipientEmail] = false;
                error_log("Failed to send email to {$recipientEmail}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Send newsletter
     */
    public function sendNewsletter(
        array $recipients,
        string $subject,
        string $content,
        ?string $preheader = null
    ): array {
        $htmlContent = $this->renderNewsletterTemplate($subject, $content, $preheader);
        $results = [];

        foreach ($recipients as $recipient) {
            $email = is_array($recipient) ? $recipient['email'] : $recipient;
            $name = is_array($recipient) ? $recipient['name'] ?? null : null;

            try {
                $message = (new Email())
                    ->from(new Address($this->defaultFromEmail, $this->defaultFromName))
                    ->to($email)
                    ->subject($subject)
                    ->html($htmlContent)
                    ->text(strip_tags($content));

                $this->mailer->send($message);
                $results[$email] = true;
            } catch (\Exception $e) {
                $results[$email] = false;
                error_log("Failed to send newsletter to {$email}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Test email configuration
     */
    public function sendTestEmail(string $to): bool
    {
        try {
            $email = (new Email())
                ->from(new Address($this->defaultFromEmail, $this->defaultFromName))
                ->to($to)
                ->subject('Test Email from Notification System')
                ->html('<h1>Test Email</h1><p>This is a test email from your notification system.</p>')
                ->text('Test Email - This is a test email from your notification system.');

            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            error_log("Failed to send test email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Prepare template variables from notification
     */
    private function prepareTemplateVariables(Notification $notification): array
    {
        $variables = [
            'title' => $notification->getTitle(),
            'message' => $notification->getMessage(),
            'user_email' => $notification->getUserEmail(),
            'action_url' => $notification->getActionUrl(),
            'action_text' => $notification->getActionText(),
            'date' => $notification->getCreatedAt()->format('d/m/Y H:i'),
            'site_name' => 'Notre Plateforme',
            'site_url' => 'https://example.com'
        ];

        // Merge with notification data
        if ($notification->getData()) {
            $variables = array_merge($variables, $notification->getData());
        }

        return $variables;
    }

    /**
     * Render basic email template
     */
    private function renderBasicEmailTemplate(Notification $notification): string
    {
        return $this->twig->render('@Notification/email/basic.html.twig', [
            'notification' => $notification,
            'title' => $notification->getTitle(),
            'message' => $notification->getMessage(),
            'action_url' => $notification->getActionUrl(),
            'action_text' => $notification->getActionText(),
        ]);
    }

    /**
     * Render newsletter template
     */
    private function renderNewsletterTemplate(string $subject, string $content, ?string $preheader = null): string
    {
        return $this->twig->render('@Notification/email/newsletter.html.twig', [
            'subject' => $subject,
            'content' => $content,
            'preheader' => $preheader,
        ]);
    }

    /**
     * Create email template
     */
    public function createTemplate(
        string $name,
        string $type,
        string $subject,
        string $htmlContent,
        string $locale = 'fr',
        ?string $textContent = null,
        ?array $variables = null,
        ?string $description = null
    ): EmailTemplate {
        return $this->templateRepository->createOrUpdate(
            $name,
            $type,
            $subject,
            $htmlContent,
            $locale,
            $textContent,
            $variables,
            $description
        );
    }

    /**
     * Get template preview
     */
    public function getTemplatePreview(string $templateName, array $variables = [], string $locale = 'fr'): array
    {
        $template = $this->templateRepository->findActiveTemplate($templateName, $locale);
        
        if (!$template) {
            throw new \InvalidArgumentException("Template '{$templateName}' not found");
        }

        // Merge with default variables
        $defaultVariables = [
            'user_email' => 'example@email.com',
            'site_name' => 'Notre Plateforme',
            'site_url' => 'https://example.com',
            'date' => (new \DateTime())->format('d/m/Y H:i')
        ];

        $variables = array_merge($defaultVariables, $variables);

        return $template->processContent($variables);
    }

    /**
     * Validate template variables
     */
    public function validateTemplate(string $templateName, array $variables = [], string $locale = 'fr'): array
    {
        $template = $this->templateRepository->findActiveTemplate($templateName, $locale);
        
        if (!$template) {
            throw new \InvalidArgumentException("Template '{$templateName}' not found");
        }

        return $template->validateVariables($variables);
    }
}