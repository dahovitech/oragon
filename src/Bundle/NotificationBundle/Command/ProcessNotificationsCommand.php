<?php

namespace App\Bundle\NotificationBundle\Command;

use App\Bundle\NotificationBundle\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'notification:process',
    description: 'Process pending notifications'
)]
class ProcessNotificationsCommand extends Command
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Number of notifications to process in each batch', 100)
            ->addOption('max-batches', 'm', InputOption::VALUE_OPTIONAL, 'Maximum number of batches to process', 10)
            ->addOption('daemon', 'd', InputOption::VALUE_NONE, 'Run in daemon mode (continuous processing)')
            ->addOption('sleep', 's', InputOption::VALUE_OPTIONAL, 'Sleep time between batches in seconds (daemon mode)', 30)
            ->setHelp('This command processes pending notifications and sends them via configured channels.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $batchSize = $input->getOption('batch-size');
        $maxBatches = $input->getOption('max-batches');
        $daemon = $input->getOption('daemon');
        $sleep = $input->getOption('sleep');

        $io->title('Processing Notifications');

        if ($daemon) {
            $io->note('Running in daemon mode. Press Ctrl+C to stop.');
            
            while (true) {
                $processed = $this->processBatch($io, $batchSize);
                
                if ($processed === 0) {
                    $io->text('No pending notifications. Sleeping...');
                }
                
                sleep($sleep);
            }
        } else {
            $totalProcessed = 0;
            $batchCount = 0;

            while ($batchCount < $maxBatches) {
                $processed = $this->processBatch($io, $batchSize);
                $totalProcessed += $processed;
                $batchCount++;

                if ($processed === 0) {
                    $io->success('No more pending notifications to process.');
                    break;
                }

                if ($processed < $batchSize) {
                    $io->success('All pending notifications processed.');
                    break;
                }
            }

            $io->success("Total notifications processed: {$totalProcessed}");
        }

        return Command::SUCCESS;
    }

    private function processBatch(SymfonyStyle $io, int $batchSize): int
    {
        try {
            $processed = $this->notificationService->processPendingNotifications($batchSize);
            
            if ($processed > 0) {
                $io->text("Processed {$processed} notifications in this batch.");
            }
            
            return $processed;
        } catch (\Exception $e) {
            $io->error("Error processing notifications: " . $e->getMessage());
            return 0;
        }
    }
}