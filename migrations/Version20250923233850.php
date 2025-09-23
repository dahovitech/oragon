<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for Blog and BlogTranslation entities - Phase 1 Multilingual Infrastructure
 */
final class Version20250923233850 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Blog and BlogTranslation entities for multilingual blog system';
    }

    public function up(Schema $schema): void
    {
        // Create blogs table
        $this->addSql('CREATE TABLE blogs (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            author_id INTEGER NOT NULL,
            is_published BOOLEAN NOT NULL DEFAULT 0,
            published_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            featured_image VARCHAR(255) DEFAULT NULL,
            view_count INTEGER NOT NULL DEFAULT 0,
            sort_order INTEGER NOT NULL DEFAULT 0,
            CONSTRAINT FK_F41BCA70F675F31B FOREIGN KEY (author_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');

        // Create index on author_id
        $this->addSql('CREATE INDEX IDX_F41BCA70F675F31B ON blogs (author_id)');
        
        // Create index on published status and date
        $this->addSql('CREATE INDEX IDX_BLOGS_PUBLISHED ON blogs (is_published, published_at)');

        // Create blog_translations table
        $this->addSql('CREATE TABLE blog_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            blog_id INTEGER NOT NULL,
            language_id INTEGER NOT NULL,
            title VARCHAR(255) NOT NULL,
            content CLOB DEFAULT NULL,
            excerpt VARCHAR(500) DEFAULT NULL,
            meta_title VARCHAR(500) DEFAULT NULL,
            meta_description CLOB DEFAULT NULL,
            slug VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            CONSTRAINT FK_BLOG_TRANSLATIONS_BLOG FOREIGN KEY (blog_id) REFERENCES blogs (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_BLOG_TRANSLATIONS_LANGUAGE FOREIGN KEY (language_id) REFERENCES languages (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');

        // Create indexes on blog_translations
        $this->addSql('CREATE INDEX IDX_BLOG_TRANSLATIONS_BLOG ON blog_translations (blog_id)');
        $this->addSql('CREATE INDEX IDX_BLOG_TRANSLATIONS_LANGUAGE ON blog_translations (language_id)');
        
        // Create unique constraint for blog_id + language_id combination
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BLOG_LANGUAGE ON blog_translations (blog_id, language_id)');
        
        // Create index on slug for SEO
        $this->addSql('CREATE INDEX IDX_BLOG_TRANSLATIONS_SLUG ON blog_translations (slug)');
    }

    public function down(Schema $schema): void
    {
        // Drop blog_translations table
        $this->addSql('DROP TABLE blog_translations');
        
        // Drop blogs table
        $this->addSql('DROP TABLE blogs');
    }
}