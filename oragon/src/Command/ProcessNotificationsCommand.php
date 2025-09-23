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
    name: 'app:notifications:process',
    description: 'Process pending notifications'
)]
class ProcessNotificationsCommand extends Command
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
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Maximum number of notifications to process', 100)
            ->addOption('retry-failed', 'r', InputOption::VALUE_NONE, 'Also retry failed notifications')
            ->addOption('max-attempts', 'm', InputOption::VALUE_OPTIONAL, 'Maximum retry attempts for failed notifications', 3)
            ->setHelp('This command processes pending notifications and optionally retries failed ones.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');
        $retryFailed = $input->getOption('retry-failed');
        $maxAttempts = (int) $input->getOption('max-attempts');

        $io->title('Processing Notifications');

        // Process scheduled notifications first
        $io->section('Processing scheduled notifications...');
        $scheduledResults = $this->notificationService->processScheduledNotifications();
        $scheduledCount = count($scheduledResults);
        $scheduledSuccess = count(array_filter($scheduledResults, fn($r) => $r['success']));

        if ($scheduledCount > 0) {
            $io->success(sprintf('Processed %d scheduled notifications (%d successful, %d failed)', 
                $scheduledCount, $scheduledSuccess, $scheduledCount - $scheduledSuccess));
        } else {
            $io->info('No scheduled notifications to process');
        }

        // Process pending notifications
        $io->section('Processing pending notifications...');
        $pendingResults = $this->notificationService->processPendingNotifications($limit);
        $pendingCount = count($pendingResults);
        $pendingSuccess = count(array_filter($pendingResults, fn($r) => $r['success']));

        if ($pendingCount > 0) {
            $io->success(sprintf('Processed %d pending notifications (%d successful, %d failed)', 
                $pendingCount, $pendingSuccess, $pendingCount - $pendingSuccess));

            // Show failed notifications details if any
            $failed = array_filter($pendingResults, fn($r) => !$r['success']);
            if (!empty($failed)) {
                $io->warning('Failed notifications:');
                foreach ($failed as $result) {
                    $notification = $result['notification'];
                    $error = $result['error'] ?? 'Unknown error';
                    $io->writeln(sprintf('  - ID %d (%s): %s', 
                        $notification->getId(), 
                        $notification->getType(), 
                        $error
                    ));
                }
            }
        } else {
            $io->info('No pending notifications to process');
        }

        // Retry failed notifications if requested
        if ($retryFailed) {
            $io->section('Retrying failed notifications...');
            $retryResults = $this->notificationService->retryFailedNotifications($maxAttempts);
            $retryCount = count($retryResults);
            $retrySuccess = count(array_filter($retryResults, fn($r) => $r['success']));

            if ($retryCount > 0) {
                $io->success(sprintf('Retried %d failed notifications (%d successful, %d still failed)', 
                    $retryCount, $retrySuccess, $retryCount - $retrySuccess));
            } else {
                $io->info('No failed notifications to retry');
            }
        }

        // Show summary
        $totalProcessed = $scheduledCount + $pendingCount + ($retryFailed ? count($retryResults ?? []) : 0);
        $totalSuccess = $scheduledSuccess + $pendingSuccess + ($retryFailed ? ($retrySuccess ?? 0) : 0);

        $io->section('Summary');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Total processed', $totalProcessed],
                ['Successful', $totalSuccess],
                ['Failed', $totalProcessed - $totalSuccess],
                ['Success rate', $totalProcessed > 0 ? sprintf('%.1f%%', ($totalSuccess / $totalProcessed) * 100) : 'N/A'],
            ]
        );

        return Command::SUCCESS;
    }
}