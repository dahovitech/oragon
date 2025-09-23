<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250923215214 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE blog_comments (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, post_id INTEGER NOT NULL, author_id INTEGER DEFAULT NULL, parent_id INTEGER DEFAULT NULL, author_name VARCHAR(255) DEFAULT NULL, author_email VARCHAR(255) DEFAULT NULL, content CLOB NOT NULL, status VARCHAR(50) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_2BC3B20D4B89032C FOREIGN KEY (post_id) REFERENCES blog_posts (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2BC3B20DF675F31B FOREIGN KEY (author_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2BC3B20D727ACA70 FOREIGN KEY (parent_id) REFERENCES blog_comments (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_2BC3B20D4B89032C ON blog_comments (post_id)');
        $this->addSql('CREATE INDEX IDX_2BC3B20DF675F31B ON blog_comments (author_id)');
        $this->addSql('CREATE INDEX IDX_2BC3B20D727ACA70 ON blog_comments (parent_id)');
        $this->addSql('CREATE TABLE blog_post_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, post_id INTEGER NOT NULL, language_id INTEGER NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, excerpt CLOB DEFAULT NULL, content CLOB NOT NULL, meta_title VARCHAR(255) DEFAULT NULL, meta_description CLOB DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_2497E3324B89032C FOREIGN KEY (post_id) REFERENCES blog_posts (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2497E33282F1BAF4 FOREIGN KEY (language_id) REFERENCES core_languages (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2497E332989D9B62 ON blog_post_translations (slug)');
        $this->addSql('CREATE INDEX IDX_2497E3324B89032C ON blog_post_translations (post_id)');
        $this->addSql('CREATE INDEX IDX_2497E33282F1BAF4 ON blog_post_translations (language_id)');
        $this->addSql('CREATE TABLE blog_posts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, featured_image_id INTEGER DEFAULT NULL, author_id INTEGER NOT NULL, category_id INTEGER DEFAULT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, excerpt CLOB DEFAULT NULL, content CLOB NOT NULL, meta_title VARCHAR(255) DEFAULT NULL, meta_description CLOB DEFAULT NULL, status VARCHAR(50) NOT NULL, is_featured BOOLEAN NOT NULL, allow_comments BOOLEAN NOT NULL, published_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , view_count INTEGER NOT NULL, CONSTRAINT FK_78B2F9323569D950 FOREIGN KEY (featured_image_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_78B2F932F675F31B FOREIGN KEY (author_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_78B2F93212469DE2 FOREIGN KEY (category_id) REFERENCES core_categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_78B2F932989D9B62 ON blog_posts (slug)');
        $this->addSql('CREATE INDEX IDX_78B2F9323569D950 ON blog_posts (featured_image_id)');
        $this->addSql('CREATE INDEX IDX_78B2F932F675F31B ON blog_posts (author_id)');
        $this->addSql('CREATE INDEX IDX_78B2F93212469DE2 ON blog_posts (category_id)');
        $this->addSql('CREATE TABLE blog_post_tags (post_id INTEGER NOT NULL, tag_id INTEGER NOT NULL, PRIMARY KEY(post_id, tag_id), CONSTRAINT FK_3971B624B89032C FOREIGN KEY (post_id) REFERENCES blog_posts (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3971B62BAD26311 FOREIGN KEY (tag_id) REFERENCES blog_tags (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3971B624B89032C ON blog_post_tags (post_id)');
        $this->addSql('CREATE INDEX IDX_3971B62BAD26311 ON blog_post_tags (tag_id)');
        $this->addSql('CREATE TABLE blog_tags (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, color VARCHAR(7) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8F6C18B65E237E06 ON blog_tags (name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8F6C18B6989D9B62 ON blog_tags (slug)');
        $this->addSql('CREATE TABLE core_categories (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, is_active BOOLEAN NOT NULL, sort_order INTEGER NOT NULL, type VARCHAR(50) NOT NULL, color VARCHAR(7) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_6BFC289727ACA70 FOREIGN KEY (parent_id) REFERENCES core_categories (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6BFC289989D9B62 ON core_categories (slug)');
        $this->addSql('CREATE INDEX IDX_6BFC289727ACA70 ON core_categories (parent_id)');
        $this->addSql('CREATE TABLE core_languages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, code VARCHAR(10) NOT NULL, locale VARCHAR(10) DEFAULT NULL, is_active BOOLEAN NOT NULL, is_default BOOLEAN NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EFB1635D77153098 ON core_languages (code)');
        $this->addSql('CREATE TABLE core_pages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, featured_image_id INTEGER DEFAULT NULL, slug VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, content CLOB DEFAULT NULL, meta_title VARCHAR(255) DEFAULT NULL, meta_description CLOB DEFAULT NULL, is_active BOOLEAN NOT NULL, is_homepage BOOLEAN NOT NULL, sort_order INTEGER NOT NULL, template VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_76F5C4953569D950 FOREIGN KEY (featured_image_id) REFERENCES media (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_76F5C495989D9B62 ON core_pages (slug)');
        $this->addSql('CREATE INDEX IDX_76F5C4953569D950 ON core_pages (featured_image_id)');
        $this->addSql('CREATE TABLE core_settings (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, setting_key VARCHAR(255) NOT NULL, setting_value CLOB DEFAULT NULL, type VARCHAR(50) NOT NULL, label VARCHAR(255) DEFAULT NULL, description CLOB DEFAULT NULL, section VARCHAR(100) DEFAULT NULL, is_public BOOLEAN NOT NULL, is_required BOOLEAN NOT NULL, default_value CLOB DEFAULT NULL, options CLOB DEFAULT NULL --(DC2Type:json)
        , sort_order INTEGER NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5A87D6665FA1E697 ON core_settings (setting_key)');
        $this->addSql('CREATE TABLE media (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, file_name VARCHAR(255) DEFAULT NULL, alt VARCHAR(255) DEFAULT NULL, extension VARCHAR(50) DEFAULT NULL, file_size INTEGER DEFAULT NULL, mime_type VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL --(DC2Type:json)
        , password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , last_login_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON user (email)');
        $this->addSql('DROP TABLE languages');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE languages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, code VARCHAR(10) NOT NULL COLLATE "BINARY", name VARCHAR(100) NOT NULL COLLATE "BINARY", native_name VARCHAR(100) NOT NULL COLLATE "BINARY", is_active BOOLEAN NOT NULL, is_default BOOLEAN NOT NULL, sort_order INTEGER NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A0D1537977153098 ON languages (code)');
        $this->addSql('DROP TABLE blog_comments');
        $this->addSql('DROP TABLE blog_post_translations');
        $this->addSql('DROP TABLE blog_posts');
        $this->addSql('DROP TABLE blog_post_tags');
        $this->addSql('DROP TABLE blog_tags');
        $this->addSql('DROP TABLE core_categories');
        $this->addSql('DROP TABLE core_languages');
        $this->addSql('DROP TABLE core_pages');
        $this->addSql('DROP TABLE core_settings');
        $this->addSql('DROP TABLE media');
        $this->addSql('DROP TABLE user');
    }
}
