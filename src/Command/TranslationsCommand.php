<?php

namespace App\Command;

use App\Service\TranslationService;
use App\Repository\LanguageRepository;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:translations',
    description: 'Import/Export translations',
)]
class TranslationsCommand extends Command
{
    public function __construct(
        private TranslationService $translationService,
        private LanguageRepository $languageRepository,
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Import or export translations')
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform (export|import)')
            ->addArgument('entity', InputArgument::REQUIRED, 'Entity type (products|categories|all)')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'CSV file path')
            ->addOption('locale', 'l', InputOption::VALUE_REQUIRED, 'Language code for import/export')
            ->addOption('format', null, InputOption::VALUE_OPTIONAL, 'Export format (csv|json)', 'csv')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $action = $input->getArgument('action');
        $entity = $input->getArgument('entity');
        $file = $input->getOption('file');
        $locale = $input->getOption('locale');
        $format = $input->getOption('format');

        $io->title('🌐 Translation Management');

        try {
            switch ($action) {
                case 'export':
                    return $this->exportTranslations($io, $entity, $file, $locale, $format);
                
                case 'import':
                    return $this->importTranslations($io, $entity, $file, $locale);
                
                default:
                    $io->error('Invalid action. Use "export" or "import"');
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function exportTranslations(SymfonyStyle $io, string $entity, ?string $file, ?string $locale, string $format): int
    {
        $io->section('📤 Exporting Translations');

        if (!$file) {
            $file = "translations_export_{$entity}_" . date('Y-m-d_H-i-s') . ".$format";
        }

        $languages = $locale ? [$this->languageRepository->findActiveByCode($locale)] : $this->languageRepository->findActiveLanguages();
        $languages = array_filter($languages); // Remove null values

        if (empty($languages)) {
            $io->error('No active languages found');
            return Command::FAILURE;
        }

        $data = [];

        switch ($entity) {
            case 'products':
                $data = $this->exportProductTranslations($languages);
                break;
            
            case 'categories':
                $data = $this->exportCategoryTranslations($languages);
                break;
            
            case 'all':
                $data = array_merge(
                    $this->exportProductTranslations($languages),
                    $this->exportCategoryTranslations($languages)
                );
                break;
            
            default:
                $io->error('Invalid entity type. Use "products", "categories", or "all"');
                return Command::FAILURE;
        }

        if (empty($data)) {
            $io->warning('No translations found to export');
            return Command::SUCCESS;
        }

        // Write to file
        if ($format === 'json') {
            $this->writeJsonFile($file, $data);
        } else {
            $this->writeCsvFile($file, $data);
        }

        $io->success("Exported " . count($data) . " translations to: $file");
        return Command::SUCCESS;
    }

    private function importTranslations(SymfonyStyle $io, string $entity, ?string $file, ?string $locale): int
    {
        $io->section('📥 Importing Translations');

        if (!$file || !file_exists($file)) {
            $io->error('File not found or not specified');
            return Command::FAILURE;
        }

        if (!$locale) {
            $io->error('Locale is required for import');
            return Command::FAILURE;
        }

        $language = $this->languageRepository->findActiveByCode($locale);
        if (!$language) {
            $io->error("Language '$locale' not found or not active");
            return Command::FAILURE;
        }

        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $data = [];

        if ($extension === 'json') {
            $data = $this->readJsonFile($file);
        } else {
            $data = $this->readCsvFile($file);
        }

        if (empty($data)) {
            $io->warning('No data found in file');
            return Command::SUCCESS;
        }

        $io->progressStart(count($data));
        $imported = 0;
        $errors = 0;

        foreach ($data as $row) {
            try {
                $success = false;
                
                if ($row['entity_type'] === 'product') {
                    $success = $this->importProductTranslation($row, $locale);
                } elseif ($row['entity_type'] === 'category') {
                    $success = $this->importCategoryTranslation($row, $locale);
                }

                if ($success) {
                    $imported++;
                } else {
                    $errors++;
                }
            } catch (\Exception $e) {
                $io->text("Error importing row: " . $e->getMessage());
                $errors++;
            }
            
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success("Imported $imported translations successfully. Errors: $errors");
        
        return Command::SUCCESS;
    }

    private function exportProductTranslations(array $languages): array
    {
        $data = [];
        $products = $this->productRepository->findAll();

        foreach ($products as $product) {
            foreach ($languages as $language) {
                $translation = $this->getProductTranslation($product, $language->getCode());
                if ($translation) {
                    $data[] = [
                        'entity_type' => 'product',
                        'entity_id' => $product->getId(),
                        'language_code' => $language->getCode(),
                        'name' => $translation->getName() ?? '',
                        'description' => $translation->getDescription() ?? '',
                        'meta_title' => $translation->getMetaTitle() ?? '',
                        'meta_description' => $translation->getMetaDescription() ?? '',
                    ];
                }
            }
        }

        return $data;
    }

    private function exportCategoryTranslations(array $languages): array
    {
        $data = [];
        $categories = $this->categoryRepository->findAll();

        foreach ($categories as $category) {
            foreach ($languages as $language) {
                $translation = $this->getCategoryTranslation($category, $language->getCode());
                if ($translation) {
                    $data[] = [
                        'entity_type' => 'category',
                        'entity_id' => $category->getId(),
                        'language_code' => $language->getCode(),
                        'name' => $translation->getName() ?? '',
                        'description' => $translation->getDescription() ?? '',
                        'meta_title' => $translation->getMetaTitle() ?? '',
                        'meta_description' => $translation->getMetaDescription() ?? '',
                    ];
                }
            }
        }

        return $data;
    }

    private function getProductTranslation($product, string $locale)
    {
        foreach ($product->getTranslations() as $translation) {
            if ($translation->getLanguage()->getCode() === $locale) {
                return $translation;
            }
        }
        return null;
    }

    private function getCategoryTranslation($category, string $locale)
    {
        foreach ($category->getTranslations() as $translation) {
            if ($translation->getLanguage()->getCode() === $locale) {
                return $translation;
            }
        }
        return null;
    }

    private function importProductTranslation(array $row, string $locale): bool
    {
        $product = $this->productRepository->find($row['entity_id']);
        if (!$product) {
            return false;
        }

        $translationData = [
            'name' => $row['name'],
            'description' => $row['description'],
            'metaTitle' => $row['meta_title'],
            'metaDescription' => $row['meta_description'],
        ];

        return $this->translationService->setTranslation($product, $locale, $translationData);
    }

    private function importCategoryTranslation(array $row, string $locale): bool
    {
        $category = $this->categoryRepository->find($row['entity_id']);
        if (!$category) {
            return false;
        }

        $translationData = [
            'name' => $row['name'],
            'description' => $row['description'],
            'metaTitle' => $row['meta_title'],
            'metaDescription' => $row['meta_description'],
        ];

        return $this->translationService->setTranslation($category, $locale, $translationData);
    }

    private function writeCsvFile(string $file, array $data): void
    {
        $handle = fopen($file, 'w');
        if (!$handle) {
            throw new \Exception("Cannot create file: $file");
        }

        if (!empty($data)) {
            // Write header
            fputcsv($handle, array_keys($data[0]));
            
            // Write data
            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
        }

        fclose($handle);
    }

    private function writeJsonFile(string $file, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($file, $json) === false) {
            throw new \Exception("Cannot write to file: $file");
        }
    }

    private function readCsvFile(string $file): array
    {
        $data = [];
        $handle = fopen($file, 'r');
        if (!$handle) {
            throw new \Exception("Cannot read file: $file");
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return [];
        }

        while (($row = fgetcsv($handle)) !== false) {
            $data[] = array_combine($header, $row);
        }

        fclose($handle);
        return $data;
    }

    private function readJsonFile(string $file): array
    {
        $content = file_get_contents($file);
        if ($content === false) {
            throw new \Exception("Cannot read file: $file");
        }

        $data = json_decode($content, true);
        if ($data === null) {
            throw new \Exception("Invalid JSON in file: $file");
        }

        return $data;
    }
}