<?php

namespace App\Command\Security;

use App\Service\Security\SecurityAuditService;
use App\Service\Performance\PerformanceOptimizationService;
use App\Service\Security\RateLimitingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:security:status',
    description: 'Display security and performance status'
)]
class SecurityStatusCommand extends Command
{
    public function __construct(
        private SecurityAuditService $auditService,
        private PerformanceOptimizationService $performanceService,
        private RateLimitingService $rateLimitingService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('detailed', 'd', InputOption::VALUE_NONE, 'Show detailed information')
            ->addOption('cache-stats', 'c', InputOption::VALUE_NONE, 'Show cache statistics')
            ->addOption('warmup', 'w', InputOption::VALUE_NONE, 'Warm up cache')
            ->addOption('recommendations', 'r', InputOption::VALUE_NONE, 'Show performance recommendations')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🔒 Security & Performance Status');

        // Security Statistics
        $this->displaySecurityStats($io, $input->getOption('detailed'));

        // Cache Statistics
        if ($input->getOption('cache-stats')) {
            $this->displayCacheStats($io);
        }

        // Warm up cache
        if ($input->getOption('warmup')) {
            $this->warmupCache($io);
        }

        // Performance recommendations
        if ($input->getOption('recommendations')) {
            $this->displayRecommendations($io);
        }

        // Rate limiting status
        $this->displayRateLimitingStatus($io);

        $io->success('Security and performance status displayed successfully');

        return Command::SUCCESS;
    }

    private function displaySecurityStats(SymfonyStyle $io, bool $detailed): void
    {
        $io->section('🛡️ Security Statistics');

        $stats = $this->auditService->getSecurityStats();

        $io->definitionList(
            ['Total Events' => $stats['total_events']],
            ['Login Attempts' => $stats['login_attempts']],
            ['Failed Logins' => $stats['failed_logins']],
            ['Successful Logins' => $stats['successful_logins']],
            ['2FA Activations' => $stats['2fa_activations']],
            ['Suspicious Activities' => $stats['suspicious_activities']],
            ['Rate Limit Violations' => $stats['rate_limit_violations']],
        );

        if ($detailed) {
            $io->text(sprintf(
                'Period: %s to %s',
                $stats['period']['from'],
                $stats['period']['to']
            ));
        }
    }

    private function displayCacheStats(SymfonyStyle $io): void
    {
        $io->section('⚡ Cache Statistics');

        $stats = $this->performanceService->getCacheStats();

        $io->definitionList(
            ['Hit Ratio' => sprintf('%.1f%%', $stats['hit_ratio'] * 100)],
            ['Miss Ratio' => sprintf('%.1f%%', $stats['miss_ratio'] * 100)],
            ['Total Keys' => number_format($stats['total_keys'])],
            ['Memory Usage' => $stats['memory_usage']],
            ['Uptime' => $stats['uptime']],
            ['Operations/sec' => number_format($stats['operations_per_second'])],
        );

        // Cache health indicator
        if ($stats['hit_ratio'] >= 0.9) {
            $io->success('Cache performance is excellent');
        } elseif ($stats['hit_ratio'] >= 0.7) {
            $io->warning('Cache performance is good but could be improved');
        } else {
            $io->error('Cache performance needs attention');
        }
    }

    private function warmupCache(SymfonyStyle $io): void
    {
        $io->section('🔥 Cache Warmup');

        $io->text('Warming up cache...');
        $results = $this->performanceService->warmupCache();

        $io->listing(array_map(
            fn($key) => "✅ $key cached",
            array_keys($results)
        ));

        $io->success(sprintf('Cache warmed up with %d items', count($results)));
    }

    private function displayRecommendations(SymfonyStyle $io): void
    {
        $io->section('💡 Performance Recommendations');

        $recommendations = $this->performanceService->getPerformanceRecommendations();

        if (empty($recommendations)) {
            $io->success('No recommendations - performance is optimal!');
            return;
        }

        foreach ($recommendations as $rec) {
            $style = match($rec['priority']) {
                'high' => 'error',
                'medium' => 'warning',
                'low' => 'note',
                default => 'info'
            };

            $io->$style(sprintf('[%s] %s: %s', 
                strtoupper($rec['priority']), 
                ucfirst($rec['type']), 
                $rec['message']
            ));
        }
    }

    private function displayRateLimitingStatus(SymfonyStyle $io): void
    {
        $io->section('🚦 Rate Limiting Status');

        // Test different rate limiters
        $testCases = [
            ['identifier' => '127.0.0.1', 'type' => 'general'],
            ['identifier' => 'test@example.com', 'type' => 'login'],
            ['identifier' => 'api_test', 'type' => 'api'],
        ];

        $rows = [];
        foreach ($testCases as $test) {
            $status = $this->rateLimitingService->getRateLimitStatus(
                $test['identifier'], 
                $test['type']
            );

            $rows[] = [
                $test['type'],
                $test['identifier'],
                $status['limit'],
                $status['remaining'],
                $status['is_available'] ? '✅ Available' : '❌ Limited',
            ];
        }

        $io->table(
            ['Type', 'Identifier', 'Limit', 'Remaining', 'Status'],
            $rows
        );
    }
}