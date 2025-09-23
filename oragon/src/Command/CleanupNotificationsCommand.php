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
    name: 'app:notifications:cleanup',
    description: 'Clean up old notifications'
)]
class CleanupNotificationsCommand extends Command
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Delete notifications older than X days', 90)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted without actually deleting')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force deletion without confirmation')
            ->setHelp('This command removes old notifications to keep the database clean.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        $io->title('Cleanup Old Notifications');

        if ($days < 1) {
            $io->error('Days must be at least 1');
            return Command::FAILURE;
        }

        $cutoffDate = new \DateTimeImmutable('-' . $days . ' days');
        
        $io->info(sprintf('Looking for notifications older than %d days (before %s)', 
            $days, $cutoffDate->format('Y-m-d H:i:s')
        ));

        // Get statistics first
        $statistics = $this->notificationService->getStatistics($cutoffDate);
        
        if ($statistics['total'] == 0) {
            $io->success('No old notifications found to clean up');
            return Command::SUCCESS;
        }

        $io->section('Notifications to be deleted:');
        $io->table(
            ['Status', 'Count'],
            [
                ['Sent', $statistics['sent']],
                ['Failed', $statistics['failed']],
                ['Read', $statistics['read']],
                ['Total', $statistics['total']],
            ]
        );

        if ($dryRun) {
            $io->note('This is a dry run. No notifications will be deleted.');
            return Command::SUCCESS;
        }

        // Ask for confirmation unless forced
        if (!$force) {
            if (!$io->confirm(sprintf('Are you sure you want to delete %d old notifications?', $statistics['total']))) {
                $io->info('Operation cancelled');
                return Command::SUCCESS;
            }
        }

        // Perform cleanup
        $io->section('Deleting old notifications...');
        
        try {
            $deleted = $this->notificationService->cleanOldNotifications($days);
            
            if ($deleted > 0) {
                $io->success(sprintf('Successfully deleted %d old notifications', $deleted));
            } else {
                $io->info('No notifications were deleted');
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error during cleanup: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}