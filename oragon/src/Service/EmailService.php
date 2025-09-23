<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\EmailTemplate;
use App\Repository\EmailTemplateRepository;
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
        string $defaultFromEmail = 'noreply@example.com',
        string $defaultFromName = 'Notre Plateforme'
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

        // Add preheader if available
        if ($processedContent['preheader']) {
            $email->getHeaders()->addTextHeader('X-MC-PreheaderText', $processedContent['preheader']);
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

            if ($processedContent['preheader']) {
                $email->getHeaders()->addTextHeader('X-MC-PreheaderText', $processedContent['preheader']);
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

                if ($preheader) {
                    $message->getHeaders()->addTextHeader('X-MC-PreheaderText', $preheader);
                }

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
            'site_name' => $this->defaultFromName,
            'site_url' => 'https://example.com'
        ];

        // Add user information if available
        if ($notification->getUser()) {
            $user = $notification->getUser();
            $variables['user_name'] = $user->getFirstName() ?? $user->getEmail();
            $variables['user_first_name'] = $user->getFirstName();
            $variables['user_last_name'] = $user->getLastName();
        }

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
        return $this->twig->render('notification/email/basic.html.twig', [
            'notification' => $notification,
            'title' => $notification->getTitle(),
            'message' => $notification->getMessage(),
            'action_url' => $notification->getActionUrl(),
            'action_text' => $notification->getActionText(),
            'user_name' => $notification->getUser() ? 
                ($notification->getUser()->getFirstName() ?? $notification->getUser()->getEmail()) : 
                $notification->getUserEmail(),
        ]);
    }

    /**
     * Render newsletter template
     */
    private function renderNewsletterTemplate(string $subject, string $content, ?string $preheader = null): string
    {
        return $this->twig->render('notification/email/newsletter.html.twig', [
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
     * Preview email template
     */
    public function previewTemplate(EmailTemplate $template, array $variables = []): array
    {
        // Use sample data if no variables provided
        $sampleVariables = array_merge([
            'title' => 'Titre d\'exemple',
            'message' => 'Ceci est un message d\'exemple pour la prévisualisation.',
            'user_name' => 'John Doe',
            'user_first_name' => 'John',
            'user_last_name' => 'Doe',
            'user_email' => 'john.doe@example.com',
            'site_name' => $this->defaultFromName,
            'site_url' => 'https://example.com',
            'date' => date('d/m/Y H:i'),
            'action_url' => '#',
            'action_text' => 'Cliquez ici'
        ], $variables);

        return $template->processContent($sampleVariables);
    }

    /**
     * Validate email template
     */
    public function validateTemplate(EmailTemplate $template, array $variables = []): array
    {
        $errors = [];
        
        try {
            // Check for required variables
            $missingVariables = $template->validateVariables($variables);
            if (!empty($missingVariables)) {
                $errors['missing_variables'] = $missingVariables;
            }

            // Try to process the template
            $processed = $template->processContent($variables);
            
            // Basic validation
            if (empty($processed['subject'])) {
                $errors['subject'] = 'Le sujet ne peut pas être vide';
            }
            
            if (empty($processed['html'])) {
                $errors['html'] = 'Le contenu HTML ne peut pas être vide';
            }

            // Check for unprocessed placeholders
            $remainingPlaceholders = [];
            foreach (['subject', 'html', 'text'] as $field) {
                if ($processed[$field]) {
                    preg_match_all('/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/', $processed[$field], $matches);
                    if (!empty($matches[1])) {
                        $remainingPlaceholders = array_merge($remainingPlaceholders, $matches[1]);
                    }
                }
            }
            
            if (!empty($remainingPlaceholders)) {
                $errors['unprocessed_placeholders'] = array_unique($remainingPlaceholders);
            }

        } catch (\Exception $e) {
            $errors['processing_error'] = $e->getMessage();
        }

        return $errors;
    }

    /**
     * Get email delivery statistics
     */
    public function getDeliveryStatistics(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        // This would require integration with email service provider APIs
        // For now, return mock statistics
        return [
            'sent' => 1000,
            'delivered' => 950,
            'opened' => 680,
            'clicked' => 120,
            'bounced' => 25,
            'complained' => 5,
            'delivery_rate' => 95.0,
            'open_rate' => 71.6,
            'click_rate' => 17.6,
            'bounce_rate' => 2.5,
            'complaint_rate' => 0.5
        ];
    }
}