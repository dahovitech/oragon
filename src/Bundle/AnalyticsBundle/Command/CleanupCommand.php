<?php

namespace App\Bundle\AnalyticsBundle\Command;

use App\Bundle\AnalyticsBundle\Repository\PageViewRepository;
use App\Bundle\AnalyticsBundle\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'analytics:cleanup',
    description: 'Clean up old analytics data to maintain database performance'
)]
class CleanupCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private PageViewRepository $pageViewRepository;
    private EventRepository $eventRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        PageViewRepository $pageViewRepository,
        EventRepository $eventRepository
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->pageViewRepository = $pageViewRepository;
        $this->eventRepository = $eventRepository;
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Number of days to keep (default: 365)', 365)
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Batch size for deletion (default: 1000)', 1000)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted without actually deleting')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Type of data to clean (pageviews, events, all)', 'all')
            ->setHelp('
This command cleans up old analytics data to maintain database performance.
It removes page views and events older than the specified number of days.

Examples:
  # Clean up data older than 1 year (default)
  php bin/console analytics:cleanup

  # Clean up data older than 90 days
  php bin/console analytics:cleanup --days=90

  # Dry run to see what would be deleted
  php bin/console analytics:cleanup --days=30 --dry-run

  # Clean only page views
  php bin/console analytics:cleanup --type=pageviews --days=180

  # Clean with smaller batch size
  php bin/console analytics:cleanup --batch-size=500
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = (int) $input->getOption('days');
        $batchSize = (int) $input->getOption('batch-size');
        $isDryRun = $input->getOption('dry-run');
        $type = $input->getOption('type');

        if ($days <= 0) {
            $io->error('Days must be a positive number');
            return Command::FAILURE;
        }

        if ($batchSize <= 0) {
            $io->error('Batch size must be a positive number');
            return Command::FAILURE;
        }

        $cutoffDate = new \DateTimeImmutable("-{$days} days");

        $io->title('Analytics Data Cleanup');
        $io->text([
            'Cutoff Date: ' . $cutoffDate->format('Y-m-d H:i:s'),
            'Type: ' . $type,
            'Batch Size: ' . $batchSize,
            'Dry Run: ' . ($isDryRun ? 'Yes' : 'No'),
        ]);

        if ($isDryRun) {
            $io->warning('DRY RUN MODE - No data will be actually deleted');
        }

        try {
            $totalDeleted = 0;

            if ($type === 'pageviews' || $type === 'all') {
                $io->section('Cleaning Page Views');
                $deletedPageViews = $this->cleanupPageViews($cutoffDate, $batchSize, $isDryRun, $io);
                $totalDeleted += $deletedPageViews;
                $io->text("Page views processed: {$deletedPageViews}");
            }

            if ($type === 'events' || $type === 'all') {
                $io->section('Cleaning Events');
                $deletedEvents = $this->cleanupEvents($cutoffDate, $batchSize, $isDryRun, $io);
                $totalDeleted += $deletedEvents;
                $io->text("Events processed: {$deletedEvents}");
            }

            if ($isDryRun) {
                $io->success("Would delete {$totalDeleted} records total");
            } else {
                $io->success("Successfully deleted {$totalDeleted} records total");
                $io->text('Running database optimization...');
                $this->optimizeDatabase();
                $io->text('Database optimization complete');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error during cleanup: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function cleanupPageViews(\DateTimeInterface $cutoffDate, int $batchSize, bool $isDryRun, SymfonyStyle $io): int
    {
        // Count total records to delete
        $totalCount = $this->pageViewRepository->createQueryBuilder('pv')
            ->select('COUNT(pv.id)')
            ->where('pv.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->getSingleScalarResult();

        if ($totalCount == 0) {
            $io->text('No page views to clean up');
            return 0;
        }

        $io->text("Found {$totalCount} page views to process");

        if ($isDryRun) {
            return $totalCount;
        }

        $deleted = 0;
        $progressBar = $io->createProgressBar($totalCount);
        $progressBar->start();

        while ($deleted < $totalCount) {
            $ids = $this->pageViewRepository->createQueryBuilder('pv')
                ->select('pv.id')
                ->where('pv.createdAt < :cutoffDate')
                ->setParameter('cutoffDate', $cutoffDate)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getSingleColumnResult();

            if (empty($ids)) {
                break;
            }

            $this->pageViewRepository->createQueryBuilder('pv')
                ->delete()
                ->where('pv.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->execute();

            $this->entityManager->clear();

            $batchDeleted = count($ids);
            $deleted += $batchDeleted;
            $progressBar->advance($batchDeleted);

            // Small delay to prevent overwhelming the database
            usleep(10000); // 10ms
        }

        $progressBar->finish();
        $io->newLine();

        return $deleted;
    }

    private function cleanupEvents(\DateTimeInterface $cutoffDate, int $batchSize, bool $isDryRun, SymfonyStyle $io): int
    {
        // Count total records to delete
        $totalCount = $this->eventRepository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->getSingleScalarResult();

        if ($totalCount == 0) {
            $io->text('No events to clean up');
            return 0;
        }

        $io->text("Found {$totalCount} events to process");

        if ($isDryRun) {
            return $totalCount;
        }

        $deleted = 0;
        $progressBar = $io->createProgressBar($totalCount);
        $progressBar->start();

        while ($deleted < $totalCount) {
            $ids = $this->eventRepository->createQueryBuilder('e')
                ->select('e.id')
                ->where('e.createdAt < :cutoffDate')
                ->setParameter('cutoffDate', $cutoffDate)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getSingleColumnResult();

            if (empty($ids)) {
                break;
            }

            $this->eventRepository->createQueryBuilder('e')
                ->delete()
                ->where('e.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->execute();

            $this->entityManager->clear();

            $batchDeleted = count($ids);
            $deleted += $batchDeleted;
            $progressBar->advance($batchDeleted);

            // Small delay to prevent overwhelming the database
            usleep(10000); // 10ms
        }

        $progressBar->finish();
        $io->newLine();

        return $deleted;
    }

    private function optimizeDatabase(): void
    {
        // For SQLite, run VACUUM to reclaim space
        if ($this->entityManager->getConnection()->getDatabasePlatform()->getName() === 'sqlite') {
            $this->entityManager->getConnection()->executeStatement('VACUUM');
        }

        // For MySQL, you might want to run OPTIMIZE TABLE
        // $this->entityManager->getConnection()->executeStatement('OPTIMIZE TABLE analytics_page_views, analytics_events');
    }
}