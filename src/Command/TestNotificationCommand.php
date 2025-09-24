<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notifications:test',
    description: 'Envoie une notification de test à un utilisateur',
)]
class TestNotificationCommand extends Command
{
    public function __construct(
        private NotificationService $notificationService,
        private UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l\'utilisateur')
            ->addOption('title', 't', InputOption::VALUE_OPTIONAL, 'Titre de la notification', 'Notification de test')
            ->addOption('message', 'm', InputOption::VALUE_OPTIONAL, 'Message de la notification', 'Ceci est une notification de test.')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Type de notification', 'info')
            ->addOption('priority', 'p', InputOption::VALUE_OPTIONAL, 'Priorité (normal, high)', 'normal')
            ->addOption('link', 'l', InputOption::VALUE_OPTIONAL, 'Lien d\'action')
            ->setHelp('Cette commande envoie une notification de test à un utilisateur spécifique.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $email = $input->getArgument('email');
        $title = $input->getOption('title');
        $message = $input->getOption('message');
        $type = $input->getOption('type');
        $priority = $input->getOption('priority');
        $link = $input->getOption('link');

        // Vérifier si l'utilisateur existe
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error(sprintf('Aucun utilisateur trouvé avec l\'email: %s', $email));
            return Command::FAILURE;
        }

        try {
            $notification = $this->notificationService->createNotification(
                $user,
                $title,
                $message,
                $type,
                $link,
                null,
                $priority
            );

            $io->success(sprintf(
                'Notification de test envoyée avec succès à %s (%s %s)',
                $user->getEmail(),
                $user->getFirstName(),
                $user->getLastName()
            ));

            $io->table(
                ['Propriété', 'Valeur'],
                [
                    ['ID', $notification->getId()],
                    ['Titre', $notification->getTitle()],
                    ['Message', $notification->getMessage()],
                    ['Type', $notification->getType()],
                    ['Priorité', $notification->getPriority()],
                    ['Lien', $notification->getActionUrl() ?: 'Aucun'],
                    ['Créé le', $notification->getCreatedAt()->format('Y-m-d H:i:s')],
                ]
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors de l\'envoi de la notification: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}