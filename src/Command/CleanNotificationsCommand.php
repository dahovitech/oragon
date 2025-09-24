<?php

namespace App\Command;

use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notifications:clean',
    description: 'Nettoie les anciennes notifications lues',
)]
class CleanNotificationsCommand extends Command
{
    public function __construct(
        private NotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Nombre de jours pour considérer une notification comme ancienne', 30)
            ->setHelp('Cette commande nettoie les notifications lues qui sont plus anciennes que le nombre de jours spécifié (30 par défaut).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');

        $io->title('Nettoyage des anciennes notifications');

        try {
            $deletedCount = $this->notificationService->cleanOldNotifications();

            if ($deletedCount > 0) {
                $io->success(sprintf(
                    '%d notification(s) ancienne(s) supprimée(s) (plus de %d jours et déjà lue(s)).',
                    $deletedCount,
                    $days
                ));
            } else {
                $io->info('Aucune notification ancienne à supprimer.');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors du nettoyage des notifications: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}