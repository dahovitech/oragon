<?php

namespace App\Command;

use App\Service\EcommerceTranslationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ecommerce:translation',
    description: 'Manage e-commerce translations'
)]
class EcommerceTranslationCommand extends Command
{
    public function __construct(
        private EcommerceTranslationService $translationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform: stats, create-missing, duplicate')
            ->addOption('language', 'l', InputOption::VALUE_REQUIRED, 'Target language code')
            ->addOption('source-language', 's', InputOption::VALUE_OPTIONAL, 'Source language code', 'fr')
            ->addOption('entity', 'e', InputOption::VALUE_OPTIONAL, 'Entity class name')
            ->addOption('entity-id', 'i', InputOption::VALUE_OPTIONAL, 'Entity ID')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        switch ($action) {
            case 'stats':
                return $this->showStatistics($io);
                
            case 'create-missing':
                $language = $input->getOption('language');
                $sourceLanguage = $input->getOption('source-language');
                
                if (!$language) {
                    $io->error('Language option is required for create-missing action');
                    return Command::FAILURE;
                }
                
                return $this->createMissingTranslations($io, $language, $sourceLanguage);
                
            case 'duplicate':
                $language = $input->getOption('language');
                $sourceLanguage = $input->getOption('source-language');
                $entity = $input->getOption('entity');
                $entityId = $input->getOption('entity-id');
                
                if (!$language || !$entity || !$entityId) {
                    $io->error('Language, entity, and entity-id options are required for duplicate action');
                    return Command::FAILURE;
                }
                
                return $this->duplicateTranslation($io, $entity, (int)$entityId, $sourceLanguage, $language);
                
            default:
                $io->error('Unknown action: ' . $action);
                $io->note('Available actions: stats, create-missing, duplicate');
                return Command::FAILURE;
        }
    }

    private function showStatistics(SymfonyStyle $io): int
    {
        $io->title('E-commerce Translation Statistics');
        
        $stats = $this->translationService->getTranslationStatistics();
        
        foreach ($stats as $entityType => $languages) {
            $io->section(ucfirst($entityType));
            
            $rows = [];
            foreach ($languages as $langCode => $data) {
                $rows[] = [
                    $langCode,
                    $data['translated'],
                    $data['total'],
                    $data['percentage'] . '%'
                ];
            }
            
            $io->table(['Language', 'Translated', 'Total', 'Progress'], $rows);
        }
        
        return Command::SUCCESS;
    }

    private function createMissingTranslations(SymfonyStyle $io, string $language, string $sourceLanguage): int
    {
        $io->title('Creating Missing Translations');
        $io->text('Target language: ' . $language);
        $io->text('Source language: ' . $sourceLanguage);
        
        $io->progressStart();
        
        $created = $this->translationService->createMissingTranslations($language, $sourceLanguage);
        
        $io->progressFinish();
        
        $io->success(sprintf('Created %d missing translations for language "%s"', $created, $language));
        
        return Command::SUCCESS;
    }

    private function duplicateTranslation(SymfonyStyle $io, string $entity, int $entityId, string $sourceLanguage, string $targetLanguage): int
    {
        $io->title('Duplicating Translation');
        $io->text('Entity: ' . $entity);
        $io->text('Entity ID: ' . $entityId);
        $io->text('From: ' . $sourceLanguage . ' → To: ' . $targetLanguage);
        
        $entityClasses = [
            'product' => \App\Entity\Product::class,
            'category' => \App\Entity\Category::class,
            'brand' => \App\Entity\Brand::class,
            'attribute' => \App\Entity\Attribute::class,
            'attribute-value' => \App\Entity\AttributeValue::class,
            'page' => \App\Entity\Page::class,
        ];
        
        if (!isset($entityClasses[$entity])) {
            $io->error('Unknown entity: ' . $entity);
            $io->note('Available entities: ' . implode(', ', array_keys($entityClasses)));
            return Command::FAILURE;
        }
        
        $success = $this->translationService->duplicateTranslation(
            $entityClasses[$entity],
            $entityId,
            $sourceLanguage,
            $targetLanguage
        );
        
        if ($success) {
            $io->success('Translation duplicated successfully');
            return Command::SUCCESS;
        } else {
            $io->error('Failed to duplicate translation. Check if source translation exists.');
            return Command::FAILURE;
        }
    }
}