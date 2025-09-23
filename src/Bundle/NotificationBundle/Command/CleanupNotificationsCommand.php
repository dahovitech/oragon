<?php

namespace App\Bundle\NotificationBundle\Command;

use App\Bundle\NotificationBundle\Repository\NotificationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'notification:cleanup',
    description: 'Clean up old notifications'
)]
class CleanupNotificationsCommand extends Command
{
    private NotificationRepository $notificationRepository;

    public function __construct(NotificationRepository $notificationRepository)
    {
        parent::__construct();
        $this->notificationRepository = $notificationRepository;
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Number of days to keep notifications', 90)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted without actually deleting')
            ->addOption('status', 's', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Only delete notifications with specific status', ['sent', 'failed'])
            ->setHelp('This command removes old notifications to keep the database clean.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $days = $input->getOption('days');
        $dryRun = $input->getOption('dry-run');
        $statuses = $input->getOption('status');

        $io->title('Notification Cleanup');

        $cutoffDate = new \DateTimeImmutable("-{$days} days");
        
        $io->text([
            "Cleaning up notifications older than {$days} days",
            "Cutoff date: " . $cutoffDate->format('Y-m-d H:i:s'),
            "Statuses to clean: " . implode(', ', $statuses)
        ]);

        // First, count what would be deleted
        $qb = $this->notificationRepository->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.createdAt < :cutoff')
            ->andWhere('n.status IN (:statuses)')
            ->setParameter('cutoff', $cutoffDate)
            ->setParameter('statuses', $statuses);

        $count = $qb->getQuery()->getSingleScalarResult();

        if ($count === 0) {
            $io->success('No notifications found to cleanup.');
            return Command::SUCCESS;
        }

        $io->warning("Found {$count} notifications to delete.");

        if ($dryRun) {
            $io->note('This is a dry run. No notifications will be actually deleted.');
            
            // Show some statistics
            $this->showStatistics($io, $cutoffDate, $statuses);
            
            return Command::SUCCESS;
        }

        if (!$io->confirm('Do you want to proceed with the deletion?')) {
            $io->note('Cleanup cancelled.');
            return Command::SUCCESS;
        }

        try {
            $deleted = $this->notificationRepository->cleanupOldNotifications($days);
            
            $io->success("Successfully deleted {$deleted} old notifications.");
        } catch (\Exception $e) {
            $io->error("Error during cleanup: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function showStatistics(SymfonyStyle $io, \DateTimeImmutable $cutoffDate, array $statuses): void
    {
        // Count by status
        $statusCounts = $this->notificationRepository->createQueryBuilder('n')
            ->select('n.status, COUNT(n.id) as count')
            ->where('n.createdAt < :cutoff')
            ->andWhere('n.status IN (:statuses)')
            ->setParameter('cutoff', $cutoffDate)
            ->setParameter('statuses', $statuses)
            ->groupBy('n.status')
            ->getQuery()
            ->getResult();

        if (!empty($statusCounts)) {
            $io->section('Breakdown by status:');
            foreach ($statusCounts as $status) {
                $io->text("  {$status['status']}: {$status['count']} notifications");
            }
        }

        // Count by type
        $typeCounts = $this->notificationRepository->createQueryBuilder('n')
            ->select('n.type, COUNT(n.id) as count')
            ->where('n.createdAt < :cutoff')
            ->andWhere('n.status IN (:statuses)')
            ->setParameter('cutoff', $cutoffDate)
            ->setParameter('statuses', $statuses)
            ->groupBy('n.type')
            ->orderBy('count', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        if (!empty($typeCounts)) {
            $io->section('Top 10 notification types to be deleted:');
            foreach ($typeCounts as $type) {
                $io->text("  {$type['type']}: {$type['count']} notifications");
            }
        }

        // Count by month
        $monthCounts = $this->notificationRepository->createQueryBuilder('n')
            ->select('DATE_FORMAT(n.createdAt, \'%Y-%m\') as month, COUNT(n.id) as count')
            ->where('n.createdAt < :cutoff')
            ->andWhere('n.status IN (:statuses)')
            ->setParameter('cutoff', $cutoffDate)
            ->setParameter('statuses', $statuses)
            ->groupBy('month')
            ->orderBy('month', 'DESC')
            ->setMaxResults(12)
            ->getQuery()
            ->getResult();

        if (!empty($monthCounts)) {
            $io->section('Breakdown by month:');
            foreach ($monthCounts as $month) {
                $io->text("  {$month['month']}: {$month['count']} notifications");
            }
        }
    }
}