<?php

namespace App\Command;

use App\Service\CacheService;
use App\Service\SeoService;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\LanguageRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:optimize',
    description: 'Optimize application performance and cache',
)]
class OptimizeCommand extends Command
{
    public function __construct(
        private CacheService $cacheService,
        private SeoService $seoService,
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private LanguageRepository $languageRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Optimize application performance')
            ->addOption('clear-cache', null, InputOption::VALUE_NONE, 'Clear all caches')
            ->addOption('warm-cache', null, InputOption::VALUE_NONE, 'Warm up caches')
            ->addOption('generate-sitemap', null, InputOption::VALUE_NONE, 'Generate sitemap')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Run all optimizations')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('🚀 Oragon Performance Optimization');

        $runAll = $input->getOption('all');
        $clearCache = $input->getOption('clear-cache') || $runAll;
        $warmCache = $input->getOption('warm-cache') || $runAll;
        $generateSitemap = $input->getOption('generate-sitemap') || $runAll;

        if (!$clearCache && !$warmCache && !$generateSitemap) {
            $io->error('Please specify at least one optimization option or use --all');
            return Command::FAILURE;
        }

        try {
            // Clear caches
            if ($clearCache) {
                $io->section('🧹 Clearing Caches');
                $this->cacheService->clearAll();
                $io->success('All caches cleared successfully');
            }

            // Warm up caches
            if ($warmCache) {
                $io->section('🔥 Warming Up Caches');
                $this->warmUpCaches($io);
                $io->success('Caches warmed up successfully');
            }

            // Generate sitemap
            if ($generateSitemap) {
                $io->section('🗺️ Generating Sitemap');
                $sitemap = $this->seoService->generateSitemap();
                file_put_contents('public/sitemap.xml', $sitemap);
                $io->success('Sitemap generated successfully');
            }

            $io->success('🎉 All optimizations completed successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error during optimization: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function warmUpCaches(SymfonyStyle $io): void
    {
        $languages = $this->languageRepository->findActiveLanguages();
        
        $io->progressStart(3);
        
        // Warm up language cache
        $io->text('Warming up languages...');
        foreach ($languages as $language) {
            // Pre-load language data
            $language->getCode();
            $language->getName();
        }
        $io->progressAdvance();

        // Warm up product cache
        $io->text('Warming up products...');
        $products = $this->productRepository->findActive();
        $count = 0;
        foreach ($products as $product) {
            if ($count >= 50) break; // Limit to avoid memory issues
            
            foreach ($languages as $language) {
                // This will populate the cache
                $productData = [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'price' => $product->getPrice(),
                ];
                $this->cacheService->setProduct($product->getId(), $language->getCode(), $productData);
            }
            $count++;
        }
        $io->progressAdvance();

        // Warm up category cache
        $io->text('Warming up categories...');
        $categories = $this->categoryRepository->findRootCategories();
        foreach ($categories as $category) {
            foreach ($languages as $language) {
                $categoryData = [
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                ];
                $this->cacheService->setCategory($category->getId(), $language->getCode(), $categoryData);
            }
        }
        $io->progressAdvance();

        $io->progressFinish();
    }
}