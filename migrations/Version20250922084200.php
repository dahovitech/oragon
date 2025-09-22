<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour ajouter les tables du système multilingue
 * - services : table principale pour les services
 * - service_translations : table des traductions pour les services
 */
final class Version20250922084200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des tables pour le système multilingue des services';
    }

    public function up(Schema $schema): void
    {
        // Table services
        $this->addSql('CREATE TABLE IF NOT EXISTS services (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 
            image_id INTEGER DEFAULT NULL, 
            slug VARCHAR(255) NOT NULL, 
            is_active BOOLEAN NOT NULL, 
            sort_order INTEGER NOT NULL, 
            created_at DATETIME NOT NULL, 
            updated_at DATETIME DEFAULT NULL,
            CONSTRAINT FK_7332E1693DA5256D FOREIGN KEY (image_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_7332E169989D9B62 ON services (slug)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_7332E1693DA5256D ON services (image_id)');

        // Table service_translations
        $this->addSql('CREATE TABLE IF NOT EXISTS service_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 
            translatable_id INTEGER NOT NULL, 
            language_id INTEGER NOT NULL, 
            title VARCHAR(255) NOT NULL, 
            description CLOB DEFAULT NULL, 
            content CLOB DEFAULT NULL, 
            meta_title VARCHAR(255) DEFAULT NULL, 
            meta_description CLOB DEFAULT NULL, 
            meta_keywords CLOB DEFAULT NULL, 
            created_at DATETIME NOT NULL, 
            updated_at DATETIME DEFAULT NULL,
            CONSTRAINT FK_191BAF622C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES services (id) NOT DEFERRABLE INITIALLY IMMEDIATE, 
            CONSTRAINT FK_191BAF6282F1BAF4 FOREIGN KEY (language_id) REFERENCES languages (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_191BAF622C2AC5D3 ON service_translations (translatable_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_191BAF6282F1BAF4 ON service_translations (language_id)');
        
        // Index unique pour éviter les doublons de traduction (service + langue)
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_TRANSLATION_SERVICE_LANG ON service_translations (translatable_id, language_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS service_translations');
        $this->addSql('DROP TABLE IF EXISTS services');
    }
}
