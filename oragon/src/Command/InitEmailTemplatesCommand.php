<?php

namespace App\Command;

use App\Service\EmailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notifications:init-templates',
    description: 'Initialize default email templates'
)]
class InitEmailTemplatesCommand extends Command
{
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('overwrite', 'o', InputOption::VALUE_NONE, 'Overwrite existing templates')
            ->addOption('locale', 'l', InputOption::VALUE_OPTIONAL, 'Locale for templates', 'fr')
            ->setHelp('This command creates default email templates for common notification types.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $overwrite = $input->getOption('overwrite');
        $locale = $input->getOption('locale');

        $io->title('Initialize Default Email Templates');

        $templates = $this->getDefaultTemplates($locale);
        $created = 0;
        $skipped = 0;

        foreach ($templates as $templateData) {
            try {
                $template = $this->emailService->createTemplate(
                    $templateData['name'],
                    $templateData['type'],
                    $templateData['subject'],
                    $templateData['html_content'],
                    $locale,
                    $templateData['text_content'] ?? null,
                    $templateData['variables'] ?? null,
                    $templateData['description'] ?? null
                );

                if (isset($templateData['preheader'])) {
                    $template->setPreheader($templateData['preheader']);
                }

                $io->writeln(sprintf('✓ Created template: %s', $templateData['name']));
                $created++;

            } catch (\Exception $e) {
                if (!$overwrite && str_contains($e->getMessage(), 'already exists')) {
                    $io->writeln(sprintf('- Skipped existing template: %s', $templateData['name']));
                    $skipped++;
                } else {
                    $io->error(sprintf('Failed to create template %s: %s', $templateData['name'], $e->getMessage()));
                }
            }
        }

        $io->section('Summary');
        $io->table(
            ['Result', 'Count'],
            [
                ['Created', $created],
                ['Skipped', $skipped],
                ['Total', count($templates)],
            ]
        );

        if ($created > 0) {
            $io->success(sprintf('Successfully created %d email templates', $created));
        }

        if ($skipped > 0) {
            $io->note(sprintf('%d templates were skipped (already exist). Use --overwrite to replace them.', $skipped));
        }

        return Command::SUCCESS;
    }

    private function getDefaultTemplates(string $locale): array
    {
        $siteUrl = 'https://example.com';
        $siteName = 'Notre Plateforme';

        return [
            [
                'name' => 'welcome',
                'type' => 'welcome',
                'subject' => 'Bienvenue sur {{ site_name }} !',
                'description' => 'Email de bienvenue pour les nouveaux utilisateurs',
                'preheader' => 'Bienvenue ! Découvrez tout ce que vous pouvez faire.',
                'variables' => [
                    ['name' => 'user_name', 'description' => 'Nom de l\'utilisateur'],
                    ['name' => 'site_name', 'description' => 'Nom du site'],
                    ['name' => 'action_url', 'description' => 'URL d\'action'],
                ],
                'html_content' => '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bienvenue</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4f46e5; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: white; padding: 30px; border: 1px solid #e2e8f0; }
        .footer { background: #f7fafc; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; }
        .button { display: inline-block; background: #4f46e5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Bienvenue sur {{ site_name }} !</h1>
        </div>
        <div class="content">
            <h2>Bonjour {{ user_name }} !</h2>
            <p>Nous sommes ravis de vous accueillir sur {{ site_name }}. Votre compte a été créé avec succès.</p>
            <p>Vous pouvez maintenant profiter de toutes les fonctionnalités de notre plateforme :</p>
            <ul>
                <li>Accès à votre tableau de bord personnalisé</li>
                <li>Gestion de vos préférences</li>
                <li>Support client prioritaire</li>
            </ul>
            <div style="text-align: center;">
                <a href="{{ action_url }}" class="button">Commencer</a>
            </div>
        </div>
        <div class="footer">
            <p>Merci de faire confiance à {{ site_name }} !</p>
        </div>
    </div>
</body>
</html>',
                'text_content' => 'Bonjour {{ user_name }},

Bienvenue sur {{ site_name }} !

Nous sommes ravis de vous accueillir. Votre compte a été créé avec succès.

Visitez votre tableau de bord : {{ action_url }}

Merci de faire confiance à {{ site_name }} !',
            ],
            [
                'name' => 'password_reset',
                'type' => 'password_reset',
                'subject' => 'Réinitialisation de votre mot de passe',
                'description' => 'Email pour la réinitialisation de mot de passe',
                'preheader' => 'Cliquez sur le lien pour réinitialiser votre mot de passe.',
                'variables' => [
                    ['name' => 'user_name', 'description' => 'Nom de l\'utilisateur'],
                    ['name' => 'action_url', 'description' => 'URL de réinitialisation'],
                ],
                'html_content' => '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Réinitialisation mot de passe</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #dc2626; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: white; padding: 30px; border: 1px solid #e2e8f0; }
        .footer { background: #f7fafc; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; }
        .button { display: inline-block; background: #dc2626; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .warning { background: #fef2f2; border: 1px solid #fecaca; padding: 15px; border-radius: 6px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Réinitialisation de mot de passe</h1>
        </div>
        <div class="content">
            <h2>Bonjour {{ user_name }},</h2>
            <p>Une demande de réinitialisation de mot de passe a été effectuée pour votre compte.</p>
            <div class="warning">
                <strong>Important :</strong> Si vous n\'avez pas fait cette demande, ignorez cet email.
            </div>
            <p>Pour réinitialiser votre mot de passe, cliquez sur le bouton ci-dessous :</p>
            <div style="text-align: center;">
                <a href="{{ action_url }}" class="button">Réinitialiser mon mot de passe</a>
            </div>
            <p><small>Ce lien expire dans 1 heure pour des raisons de sécurité.</small></p>
        </div>
        <div class="footer">
            <p>{{ site_name }} - Équipe sécurité</p>
        </div>
    </div>
</body>
</html>',
                'text_content' => 'Bonjour {{ user_name }},

Une demande de réinitialisation de mot de passe a été effectuée pour votre compte.

Si vous n\'avez pas fait cette demande, ignorez cet email.

Pour réinitialiser votre mot de passe, visitez : {{ action_url }}

Ce lien expire dans 1 heure.

{{ site_name }} - Équipe sécurité',
            ],
            [
                'name' => 'order_confirmation',
                'type' => 'order_confirmation',
                'subject' => 'Confirmation de votre commande #{{ order_number }}',
                'description' => 'Email de confirmation de commande',
                'preheader' => 'Votre commande a été confirmée et sera traitée rapidement.',
                'variables' => [
                    ['name' => 'user_name', 'description' => 'Nom du client'],
                    ['name' => 'order_number', 'description' => 'Numéro de commande'],
                    ['name' => 'order_total', 'description' => 'Montant total'],
                    ['name' => 'action_url', 'description' => 'URL de suivi'],
                ],
                'html_content' => '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Confirmation de commande</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #059669; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: white; padding: 30px; border: 1px solid #e2e8f0; }
        .footer { background: #f7fafc; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; }
        .button { display: inline-block; background: #059669; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .order-info { background: #f0fdf4; padding: 20px; border-radius: 6px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✓ Commande confirmée !</h1>
        </div>
        <div class="content">
            <h2>Bonjour {{ user_name }},</h2>
            <p>Nous avons bien reçu votre commande et elle est en cours de traitement.</p>
            <div class="order-info">
                <h3>Détails de votre commande</h3>
                <p><strong>Numéro :</strong> {{ order_number }}</p>
                <p><strong>Montant :</strong> {{ order_total }}</p>
                <p><strong>Statut :</strong> Confirmée</p>
            </div>
            <p>Vous recevrez un email de confirmation d\'expédition dès que votre commande sera envoyée.</p>
            <div style="text-align: center;">
                <a href="{{ action_url }}" class="button">Suivre ma commande</a>
            </div>
        </div>
        <div class="footer">
            <p>Merci pour votre confiance !</p>
        </div>
    </div>
</body>
</html>',
            ],
            [
                'name' => 'system_alert',
                'type' => 'system_alert',
                'subject' => 'Alerte système - {{ title }}',
                'description' => 'Template pour les alertes système importantes',
                'preheader' => 'Information importante concernant votre compte.',
                'variables' => [
                    ['name' => 'user_name', 'description' => 'Nom de l\'utilisateur'],
                    ['name' => 'title', 'description' => 'Titre de l\'alerte'],
                    ['name' => 'message', 'description' => 'Message de l\'alerte'],
                    ['name' => 'action_url', 'description' => 'URL d\'action si applicable'],
                ],
                'html_content' => '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Alerte système</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f59e0b; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: white; padding: 30px; border: 1px solid #e2e8f0; }
        .footer { background: #f7fafc; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; }
        .button { display: inline-block; background: #f59e0b; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .alert { background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 6px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ Alerte Système</h1>
        </div>
        <div class="content">
            <h2>{{ title }}</h2>
            <div class="alert">
                {{ message }}
            </div>
            {% if action_url %}
            <div style="text-align: center;">
                <a href="{{ action_url }}" class="button">Voir les détails</a>
            </div>
            {% endif %}
        </div>
        <div class="footer">
            <p>{{ site_name }} - Équipe technique</p>
        </div>
    </div>
</body>
</html>',
            ]
        ];
    }
}