<?php

namespace App\Bundle\AnalyticsBundle\Command;

use App\Bundle\AnalyticsBundle\Service\ReportGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'analytics:generate-report',
    description: 'Generate analytics reports for specified date range and type'
)]
class GenerateReportCommand extends Command
{
    private ReportGenerator $reportGenerator;

    public function __construct(ReportGenerator $reportGenerator)
    {
        parent::__construct();
        $this->reportGenerator = $reportGenerator;
    }

    protected function configure(): void
    {
        $this
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Report type (dashboard, traffic, content, ecommerce, users)', 'dashboard')
            ->addOption('from', 'f', InputOption::VALUE_OPTIONAL, 'Start date (Y-m-d format)', null)
            ->addOption('to', null, InputOption::VALUE_OPTIONAL, 'End date (Y-m-d format)', null)
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path', null)
            ->addOption('format', null, InputOption::VALUE_OPTIONAL, 'Output format (json, csv)', 'json')
            ->setHelp('
This command generates analytics reports for specified date ranges and types.

Examples:
  # Generate dashboard report for last 30 days
  php bin/console analytics:generate-report --type=dashboard

  # Generate traffic report for specific date range
  php bin/console analytics:generate-report --type=traffic --from=2024-01-01 --to=2024-01-31

  # Generate e-commerce report and save to file
  php bin/console analytics:generate-report --type=ecommerce --output=/tmp/ecommerce_report.json

  # Generate CSV report
  php bin/console analytics:generate-report --type=users --format=csv --output=/tmp/users_report.csv
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $type = $input->getOption('type');
        $fromDate = $input->getOption('from');
        $toDate = $input->getOption('to');
        $outputFile = $input->getOption('output');
        $format = $input->getOption('format');

        // Set default date range if not provided
        if (!$fromDate) {
            $fromDate = (new \DateTimeImmutable('-30 days'))->format('Y-m-d');
        }
        if (!$toDate) {
            $toDate = (new \DateTimeImmutable())->format('Y-m-d');
        }

        try {
            $from = new \DateTimeImmutable($fromDate);
            $to = new \DateTimeImmutable($toDate);
        } catch (\Exception $e) {
            $io->error('Invalid date format. Please use Y-m-d format (e.g., 2024-01-01)');
            return Command::FAILURE;
        }

        $io->title('Analytics Report Generator');
        $io->text([
            'Report Type: ' . $type,
            'Date Range: ' . $from->format('Y-m-d') . ' to ' . $to->format('Y-m-d'),
            'Format: ' . $format,
        ]);

        try {
            $io->text('Generating report...');
            $data = $this->generateReport($type, $from, $to);

            if ($outputFile) {
                $this->saveToFile($data, $outputFile, $format);
                $io->success('Report saved to: ' . $outputFile);
            } else {
                $this->displayReport($io, $data, $format);
            }

            $io->success('Report generated successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error generating report: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function generateReport(string $type, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        switch ($type) {
            case 'dashboard':
                return $this->reportGenerator->generateDashboard($from, $to);
            case 'traffic':
                return $this->reportGenerator->getTrafficReport($from, $to);
            case 'content':
                return $this->reportGenerator->getContentReport($from, $to);
            case 'ecommerce':
                return $this->reportGenerator->getEcommerceReport($from, $to);
            case 'users':
                return $this->reportGenerator->getUserReport($from, $to);
            default:
                throw new \InvalidArgumentException('Invalid report type: ' . $type);
        }
    }

    private function saveToFile(array $data, string $filePath, string $format): void
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        switch ($format) {
            case 'json':
                file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
                break;
            case 'csv':
                $this->saveToCsv($data, $filePath);
                break;
            default:
                throw new \InvalidArgumentException('Invalid format: ' . $format);
        }
    }

    private function saveToCsv(array $data, string $filePath): void
    {
        $handle = fopen($filePath, 'w');
        
        // Flatten the data structure for CSV
        $flatData = $this->flattenArray($data);
        
        if (!empty($flatData)) {
            // Write headers
            fputcsv($handle, ['Metric', 'Value']);
            
            // Write data
            foreach ($flatData as $key => $value) {
                if (is_scalar($value)) {
                    fputcsv($handle, [$key, $value]);
                }
            }
        }
        
        fclose($handle);
    }

    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        
        foreach ($array as $key => $value) {
            $newKey = $prefix ? $prefix . '.' . $key : $key;
            
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }

    private function displayReport(SymfonyStyle $io, array $data, string $format): void
    {
        switch ($format) {
            case 'json':
                $io->text(json_encode($data, JSON_PRETTY_PRINT));
                break;
            case 'csv':
                $flatData = $this->flattenArray($data);
                $io->table(['Metric', 'Value'], array_map(
                    fn($key, $value) => [$key, is_scalar($value) ? $value : json_encode($value)],
                    array_keys($flatData),
                    array_values($flatData)
                ));
                break;
        }
    }
}